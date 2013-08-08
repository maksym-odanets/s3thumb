<?php
/**
 * Класс для адаптивного уменьшения изображения
 *
 * @author Оданец Максим <wolfling@i.ua>
 * @version 1.0
 */
class CThumb
{
    // исходное изображение
    private $_src = NULL;
    private $_width = 0;
    private $_height = 0;
    private $_oldImage = NULL;

    // уменьшенное изображение
    private $_newDimensions = array(
        "width" => 0,
        "height" => 0,
        "cropX" => 0,
        "cropY" => 0
    );

    // рабочие переменные
    private $_fileName = NULL;
    private $_fileInfo = array();
    private $_workingImage = NULL;

    /**
     * Загружает удаленную картинку в runtime диреккторию
     * и ожидает дальнейших действий:
     * ->save($path) - сохранить по указанному пути, @see CThumb::save
     * ->show() - вывести на экран, @see CThumb::show
     * ->resize($width, $height) - адаптиыно изменить размеры, @see CThumb::resize
     *
     * @param string путь к изображению
     */
    public static function create($src)
    {
        return new CThumb($src);
    }

    /**
     * Возвращает путь к временному файлу
     */
    public function getTmpFilePath()
    {
        return $this -> _fileName;
    }

    /**
     * MIME файла
     */
    public function getTmpFileMime()
    {
        return $this -> _fileInfo['mime'];
    }

    /**
     * Отображает картинку
     */
    public function show($quality = 85)
    {
        header('Content-Type: ' . $this -> _fileInfo['mime']);
        switch ( $this->_fileInfo['format'] )
        {
            case 'GIF' :
                imagegif($this -> _oldImage, NULL, $quality);
                break;
            case 'JPG' :
                imagejpeg($this -> _oldImage, '', $quality);
                break;
            case 'PNG' :
                imagesavealpha($this -> _oldImage, true);
                imagefill($this -> _oldImage, 0, 0, imagecolorallocatealpha($this -> _oldImage, 0, 0, 0, 127));
                imagepng($this -> _oldImage);
                break;
        }
        exit ;
    }

    /**
     * Сохраняет картинку
     *
     * @param string абсолютный путь к изображению
     */
    public function save($path = NULL, $quality = 85)
    {
        if ($path == NULL)
            $path = $this -> _fileName;

        switch ( $this->_fileInfo['format'] )
        {
            case 'GIF' :
                imagegif($this -> _oldImage, $path, $quality);
                break;
            case 'JPG' :
                imagejpeg($this -> _oldImage, $path, $quality);
                break;
            case 'PNG' :
                imagesavealpha($this -> _oldImage, true);
                imagefill($this -> _oldImage, 0, 0, imagecolorallocatealpha($this -> _oldImage, 0, 0, 0, 127));
                imagepng($this -> _oldImage, $path);
                break;
        }

        return $this;
    }

    /**
     * Конструктор
     *
     * @param string ссылка на изображение на удаленном сайте или абсолютный путь к изображению
     */
    function __construct($src)
    {
        $this -> _src = $src;

        if (preg_match('/http(s)?:\/\//', $src))
            $this -> _fileName = $this -> getRemoteFile();
        else
            $this -> _fileName = $src;

        $this -> getFileInfo();
    }

    /**
     * Адаптивно уменьшаем изображение
     *
     * @param int ширина
     * @param int высота
     */
    public function resize($width, $height)
    {
        $this -> _width = $width;
        $this -> _height = $height != 0 ? $height : $width;

        // открываем текущую картинку
        switch ( $this->_fileInfo['format'] )
        {
            case 'GIF' :
                $this -> _oldImage = imagecreatefromgif($this -> _fileName);
                break;
            case 'JPG' :
                $this -> _oldImage = imagecreatefromjpeg($this -> _fileName);
                break;
            case 'PNG' :
                $this -> _oldImage = imagecreatefrompng($this -> _fileName);
                break;
        }

        // проверяем размеры
        if ($width == 0 && $height == 0)
            return $this;

        // расчитывавем размеры и crop
        $this -> calcNewSize();
        $this -> calcNewCrop();

        // создаем рабочую картинку
        if (function_exists('imagecreatetruecolor'))
            $this -> _workingImage = imagecreatetruecolor($this -> _width, $this -> _height);
        else
            $this -> _workingImage = imagecreate($this -> _width, $this -> _height);

        // уменьшаем картинку
        $this -> _resize($this -> _newDimensions["width"], $this -> _newDimensions["height"], $this -> _newDimensions["cropX"], $this -> _newDimensions["cropY"]);

        return $this;
    }

    /**
     * Загружает удаленную картинку
     */
    private function getRemoteFile()
    {
        $ch = curl_init($this -> _src);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)');
        $content = curl_exec($ch);

        $fileName = tempnam(sys_get_temp_dir(), 'img_');
        file_put_contents($fileName, $content);

        return $fileName;
    }

    /**
     * Сохраняет информацию об изображении в $_fileInfo @see CThumb::$_fileInfo
     */
    private function getFileInfo()
    {
        $fileInfo = getimagesize($this -> _fileName);

        $this -> _fileInfo['width'] = $fileInfo[0];
        $this -> _fileInfo['height'] = $fileInfo[1];

        switch ( $fileInfo['mime'] )
        {
            case 'image/gif' :
                $this -> _fileInfo['format'] = 'GIF';
                $this -> _fileInfo['mime'] = 'image/gif';
                break;
            case 'image/jpeg' :
                $this -> _fileInfo['format'] = 'JPG';
                $this -> _fileInfo['mime'] = 'image/jpeg';
                break;
            case 'image/png' :
                $this -> _fileInfo['format'] = 'PNG';
                $this -> _fileInfo['mime'] = 'image/png';
                break;
            default :
                $this -> _fileInfo['format'] = NULL;
                $this -> _fileInfo['mime'] = NULL;
        }
    }

    /**
     * Расчитывает новые пропорции для обрезки
     */
    private function calcNewCrop()
    {
        $newCropX = 0;
        $newCropY = 0;

        $koeY = ($this -> _newDimensions["height"] > $this -> _newDimensions["width"]) ? $this -> _newDimensions["width"] / $this -> _newDimensions["height"] : 0;

        $newCropY = ceil($this -> _newDimensions["height"] / 2 - $this -> _height / 2);
        $newCropY = ceil($newCropY - $koeY * $newCropY);
        if ($newCropY < 0 || ($this -> _newDimensions["height"] - $newCropY) < $this -> _height)
            $newCropY = 0;

        $newCropX = ceil($this -> _newDimensions["width"] / 2 - $this -> _width / 2);
        if ($newCropX < 0)
            $newCropX = 0;

        $this -> _newDimensions["cropX"] = $newCropX;
        $this -> _newDimensions["cropY"] = $newCropY;
    }

    /**
     * Расчитывает новые размеры
     */
    private function calcNewSize()
    {
        $newWidth = 0;
        $newHeight = 0;

        // новое изображение вписывается в текущие размеры
        if ($this -> _fileInfo['width'] >= $this -> _width && $this -> _fileInfo['height'] >= $this -> _height)
        {
            // длина больше высоты
            if ($this -> _width >= $this -> _height)
            {
                $newWidth = $this -> _width;
                $newHeight = $this -> calcHeight($this -> _fileInfo['width'], $this -> _fileInfo['height'], $newWidth);

                // дополнительная проверка
                if ($newHeight < $this -> _height)
                {
                    $newHeight = $this -> _height;
                    $newWidth = $this -> calcWidth($this -> _fileInfo['width'], $this -> _fileInfo['height'], $newHeight);
                }
            }
            else
            {
                $newHeight = $this -> _height;
                $newWidth = $this -> calcWidth($this -> _fileInfo['width'], $this -> _fileInfo['height'], $newHeight);

                if ($newWidth < $this -> _width)
                {
                    $newWidth = $this -> _width;
                    $newHeight = $this -> calcHeight($this -> _fileInfo['width'], $this -> _fileInfo['height'], $newWidth);
                }
            }

            $this -> _newDimensions["width"] = $newWidth;
            $this -> _newDimensions["height"] = $newHeight;
        }
        else
        {
            // длина больше высоты
            if ($this -> _fileInfo['width'] >= $this -> _fileInfo['height'])
            {
                $newWidth = $this -> _fileInfo['width'];
                $newHeight = $this -> calcHeight($this -> _width, $this -> _height, $newWidth);

                // дополнительная проверка
                if ($newHeight > $this -> _fileInfo['height'])
                {
                    $newHeight = $this -> _fileInfo['height'];
                    $newWidth = $this -> calcWidth($this -> _width, $this -> _height, $newHeight);
                }
            }
            else
            {
                $newHeight = $this -> _fileInfo['height'];
                $newWidth = $this -> calcWidth($this -> _width, $this -> _height, $newHeight);

                if ($newWidth > $this -> _fileInfo['width'])
                {
                    $newWidth = $this -> _fileInfo['width'];
                    $newHeight = $this -> calcHeight($this -> _width, $this -> _height, $newWidth);
                }
            }

            $this -> _width = $newWidth;
            $this -> _height = $newHeight;

            $this -> calcNewSize();
        }
    }

    /**
     * Расчитывает высоту
     */
    private function calcHeight($curWidth, $curHeight, $newWidth)
    {
        $koe = $curWidth / $newWidth;
        $newHeight = $curHeight / $koe;

        return ceil($newHeight);
    }

    /**
     * Расчитывает ширину
     */
    private function calcWidth($curWidth, $curHeight, $newHeight)
    {
        $koe = $curHeight / $newHeight;
        $newWidth = $curWidth / $koe;

        return ceil($newWidth);
    }

    /**
     * Уменьшаем изображение
     *
     * @param int новая ширина
     * @param int новая высота
     * @param int обрезка по X
     * @param int обрезка по Y
     */
    private function _resize($width, $height, $cropX = 0, $cropY = 0)
    {
        imagecopyresampled($this -> _workingImage, $this -> _oldImage, 0, 0, $cropX, $cropY, $width, $height, $this -> _fileInfo['width'], $this -> _fileInfo['height']);

        $this -> _fileInfo['width'] = $width;
        $this -> _fileInfo['height'] = $height;
        $this -> _oldImage = $this -> _workingImage;
    }

    /**
     * Деструктор
     */
    function __destruct()
    {
        if (is_file($this -> _fileName))
            unlink($this -> _fileName);

        if (is_resource($this -> _oldImage))
            imagedestroy($this -> _oldImage);

        if (is_resource($this -> _workingImage))
            imagedestroy($this -> _workingImage);
    }

}
