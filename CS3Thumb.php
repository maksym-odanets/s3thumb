<?php
/**
 * Класс для адаптивного уменьшения изображения
 *
 * @author Оданец Максим <wolfling@i.ua>
 * @version 1.0
 */

require_once ('source/CThumb.php');
require_once ('amazon/S3.php');
require_once ('amazon/CRC4Crypt.php');

class CS3Thumb
{
	private $_s3 = NULL;
	private $_s3AccessKey;
	private $_s3SecretKey;

	private $_cryptpsw;
	private $_backets;

	/**
	 * Конструктор
	 *
	 * @param array массив с бакетами
	 * @param string accessKey пользователя S3
	 * @param string secretKey пользователя S3
	 * @param string пароль от крипта
	 */
	function __construct($backets, $accessKey, $secretKey, $cryptpsw = 'password')
	{
		$this -> _backets = $backets;
		$this -> _s3AccessKey = $accessKey;
		$this -> _s3SecretKey = $secretKey;
		$this -> _cryptpsw = $cryptpsw;
	}

	/**
	 * Создает объект S3 для сохранения уменьшенных изображений
	 *
	 * @return object
	 */
	private function s3()
	{
		if ($this -> _s3 == NULL)
		{
			$this -> _s3 = new S3($this -> _s3AccessKey, $this -> _s3SecretKey);
		}

		return $this -> _s3;
	}

	/**
	 * Кодирует ссылку
	 *
	 * @return string
	 */
	private function urlEncode($url)
	{
		return str_replace(array(
			'+',
			'=',
			'/'
		), array(
			'-',
			'_',
			'~'
		), CRC4Crypt::base64_encrypt($this -> _cryptpsw, $url));
	}

	/**
	 * Деодирует ссылку
	 *
	 * @return string
	 */
	private function urlDecode($url)
	{
		return CRC4Crypt::base64_decrypt($this -> _cryptpsw, str_replace(array(
			'-',
			'_',
			'~'
		), array(
			'+',
			'=',
			'/'
		), $url));
	}

	/**
	 * Возвращает расширение файла
	 *
	 * @param string ссылка
	 */
	private function ext($url)
	{
		return mb_substr($url, 1 + mb_strrpos($url, "."));
	}

	/**
	 * Генерируем бакет
	 *
	 * @return string
	 */
	private function getBacket($fl)
	{
		$ab = array(
			'0',
			'1',
			'2',
			'3',
			'4',
			'5',
			'6',
			'7',
			'8',
			'9',
			'a',
			'b',
			'c',
			'd',
			'e',
			'f'
		);

		$key = array_search($fl, $ab);
		$col = 16 / sizeof($this -> _backets);
		$id = sizeof($this -> _backets) - ceil((16 - $key) / $col);

		return $this -> _backets[$id];
	}

	/**
	 * Создает удаленную ссылку на уменьшенное изображение
	 *
	 * @param string ссылка
	 * @param int ширина
	 * @param int высота
	 * @return string ссылка на уменьшенное изображение
	 */
	public function url($url, $width = 0, $height = 0)
	{
		$height = $height != 0 ? $height : $width;
		$ext = $this -> ext($url);
		$url = str_replace(array(
			'.' . $ext,
			'http://'
		), '', $url);
		$crypt = $width . ':' . $height . '@' . $url;
		$path = $this -> urlEncode($crypt) . '.' . $ext;

		return 'http://' . $this -> getBacket(mb_substr(md5($path), 0, 1)) . '/' . mb_substr(md5($path), 0, 2) . '/' . $path;
	}

	/**
	 * Сохраняет уменьшенное изображение и отображает его
	 *
	 * @param string зашифрованный CLocalThumb::urlEncode путь по которому нужно сохранить уменьшенное изображение
	 * @param int ширина
	 * @param int высота
	 */
	public function process($path)
	{
		$params = preg_split('/(.*?)\/((.*?)\.(.*))/', $path, null, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);

		$folder = $params[0];
		$file = $params[1];
		$ext = $params[3];
		$decrypt = preg_split('/(.*?):(.*?)@(.*)/', $this -> urlDecode($params[2]), null, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);

		// проверка на верность крипта
		if (empty($decrypt[2]))
			return false;

		$width = $decrypt[0];
		$height = $decrypt[1];
		$src = preg_match('/:\/\//', $decrypt[2]) ? $decrypt[2] . '.' . $ext : 'http://' . $decrypt[2] . '.' . $ext;

		$uri = $folder . '/' . $file;
		$bucket = $this -> getBacket(mb_substr($folder, 0, 1));

		$thumb = CThumb::create($src);
		$mime = $thumb -> getTmpFileMime();

		if ($mime == NULL)
			return false;

		$localFile = $thumb -> getTmpFilePath();
		$thumb -> resize($width, $height) -> save(NULL, 65);

		$put = $this -> s3() -> putObject($this -> s3() -> inputFile($localFile), $bucket, $uri, S3::ACL_PUBLIC_READ, array(), array(
			"Cache-Control" => "max-age=315360000",
			"Expires" => gmdate("D, d M Y H:i:s T", strtotime("+5 years")),
			"Content-Type" => $mime
		));

		$thumb -> show();
	}

}
