<?php
namespace Rodler\ImageManger;

class ImageManager
{
    protected $mimes= array(
        'image/jpeg'    => 'imagecreatefromjpeg',
        'image/pjpeg'   => 'imagecreatefromjpeg',
        'image/png'     => 'imagecreatefrompng',
        'image/gif'     => 'imagecreatefromgif',
        'image/x-png'   => 'imagecreatefrompng',
        'image/x-gif'   => 'imagecreatefromgif'
    );

    protected $view= array(
        'image/jpeg'  => 'imagejpeg',
        'image/pjpeg' => 'imagejpeg',
        'image/png'   => 'imagepng',
        'image/gif'   => 'imagegif',
        'image/x-png' => 'imagepng',
        'image/x-gif' => 'imagegif'
    );

    protected $resize = false;

    public function created($image, $directory, $settings) {
        $file = $this->loadFile($image['tmp_name']);

        $data = getimagesize($image['tmp_name']);
        $canvas = null;
        foreach($settings as $key => $setting) {
            $this->is_resize($setting['nombre'],$data['mime'],$image['tmp_name']);
            if($this->resize)
                $canvas = $this->resizeImage($image, $file, $setting['ancho'], $setting['alto'], $setting['escalar'], $setting['cortar'], $setting['expand']);
            $this->saveFile($canvas,$directory,$setting['quality'],$setting['nombre'], $key, $setting['nombre'].'_'.basename($image['name']), $image);
            if( $setting['watermark'])
            {
                $this->setWatermark( $image, $directory, $setting['nombre'], $setting['quality'], $setting['vpos'].' '.$setting['hpos']  );
                $this->getSizes();
                $this->applyWatermark();
                $this->generate( $this->file['image']);
            }
        }
    }
    protected function setWatermark( $image, $directory, $nombre, $quality, $vpos='bottom', $hpos='center')
    {
        $this->file['image'] = $directory.'/'.$nombre.'_'.basename($image['name']);
        $this->quality = $quality;
        $f = pathinfo( $this->file['image']);
        $this->extension['image'] = $f['extension'];
        $this->image = $this->createImage($this->file['image']);
        $this->file['watermark'] = sfConfig::get('sf_web_dir').'/images/'.$nombre.'_watermark.png';
        $this->position = $vpos.' '.$hpos;
        $this->size['watermark'] = '100%';
    }
    protected function resizeImage($image, $file, $maxWidth, $maxHeight, $scale, $cut, $expand,$offset=false)
    {
        $data = getimagesize($image['tmp_name']);
        $originalWidth = $data[0];
        $originalHeight = $data[1];
        $centralWidth = $originalWidth/2;
        $centralHeight = $originalHeight/2;
        $mime = $data['mime'];
        $originalAspect = $originalWidth/$originalHeight;
        $baseAspect = $maxWidth/$maxHeight;
        if($expand || $originalWidth >$maxWidth || $originalHeight>$maxHeight)
        {
            if($scale && $cut)
            {
                if($originalAspect > $baseAspect)
                {
                    $autoHeight = $maxHeight;
                    $autoWidth = $autoHeight*$originalAspect;
                }
                else
                {
                    $autoWidth = $maxWidth;
                    $autoHeight = $autoWidth/$originalAspect;
                }


                $xCopy=$centralWidth-(($maxWidth/2)*($originalWidth/$autoWidth));
                $yCopy=$centralHeight-(($maxHeight/2)*($originalWidth/$autoWidth));

                $canvas = $this->createCanvas($maxWidth ,$maxHeight);
                imagecopyresampled($canvas, $file, 0, 0, $xCopy, $yCopy,$autoWidth ,$autoHeight , $originalWidth, $originalHeight);
            }

            if($scale && !$cut)
            {
                if($originalAspect > $baseAspect)
                {
                    $autoWidth = $maxWidth;
                    $autoHeight = $autoWidth/$originalAspect;
                }
                else
                {
                    $autoHeight = $maxHeight;
                    $autoWidth = $autoHeight*$originalAspect;
                }
                $canvas = $this->createCanvas($autoWidth ,$autoHeight);
                imagecopyresampled($canvas, $file, 0, 0, 0, 0,$autoWidth ,$autoHeight , $originalWidth, $originalHeight);
            }

            if(!$scale && $cut)
            {
                $xCopy = $centralWidth-($maxWidth/2);
                $yCopy = $centralHeight-($maxHeight/2);

                $canvas = $this->createCanvas($maxWidth,$maxHeight);
                imagecopyresampled($canvas, $file, 0, 0, $xCopy, $yCopy,$originalWidth ,$originalHeight, $originalWidth, $originalHeight);
            }

            if(!$scale && !$cut)
            {
                $canvas = $this->createCanvas($originalWidth,$originalHeight);
                imagecopyresampled($canvas, $file, 0, 0, 0, 0,$originalWidth ,$originalHeight , $originalWidth, $originalHeight);
            }
        }
        else
        {
            $canvas = $this->createCanvas($originalWidth,$originalHeight);
            imagecopyresampled($canvas, $file, 0, 0, 0, 0,$originalWidth ,$originalHeight , $originalWidth, $originalHeight);
        }
        return $canvas;
    }

    protected function is_resize($prefix,$mime,$tmpFile)
    {
        if(($mime == 'image/x-gif' || $mime == 'image/gif') && $this->is_animate($tmpFile))
            if($prefix == 'thumb')
                $this->resize = true;
            else
                $this->resize = false;
        else
            $this->resize = true;
    }

    protected function is_animate($filename) {
        if(!($fh = @fopen($filename, 'rb')))
            return false;
        $count = 0;
        while(!feof($fh) && $count < 2) {
            $chunk = fread($fh, 1024 * 100); //read 100kb at a time
            $count += preg_match_all('#\x00\x21\xF9\x04.{4}\x00\x2C#s', $chunk, $matches);
        }
        fclose($fh);
        return $count > 1;
    }


    protected function createCanvas($w,$h)
    {
        $canvas = imagecreatetruecolor($w, $h);
        imagealphablending($canvas, false );
        imagesavealpha($canvas, true );
        $transparent = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
        imagefilledrectangle($canvas, 0, 0, $w, $h, $transparent);
        imagecolortransparent($canvas, $transparent);
        return $canvas;
    }

    protected function saveFile($canvas,$directory,$quality,$prefix,$key, $name, $image)
    {
        $fileTo = $directory.'/'.$name;
        $data = getimagesize($image['tmp_name']);
        $this->is_resize($prefix,$data['mime'],$image['tmp_name']);
        if(!$this->resize)
            copy($image['tmp_name'], $fileTo);
        else
        {
            if(isset($this->view[$data['mime']]))
            {
                $recoder = $this->view[$data['mime']];
                if(!function_exists($recoder))
                    die("error 0001: no existe recoder");
                if($recoder == 'imagepng')
                {
                    $quality = ceil(9-($quality*9)/100);
                }
                return $recoder($canvas, $fileTo, $quality);
            }
            else
                die("error 0002: no existe recoder");
        }
    }

    protected function loadFile($image)
    {
        $data = getimagesize($image);
        if(isset($this->mimes[$data['mime']]))
        {
            $loader = $this->mimes[$data['mime']];
            if(!function_exists($loader))
                throw new exception("error 0001: no existe loader");
            return $loader($image);
        }
        else
            throw new exception("error 0002: no existe loader");
    }
    private function getSizes()
    {
        if ( !empty($this->file['image']) )
        {
            list($width, $height, $format) = getimagesize($this->file['image']);
            $this->current_size['image'] = compact('width', 'height', 'format');
        }

        if ( !empty($this->file['watermark']) )
        {
            list($width, $height, $format) = getimagesize($this->file['watermark']);
            $this->current_size['watermark'] = compact('width', 'height', 'format');

            if ( !empty($this->size['watermark']) )
            {
                // Size in percentage
                if ( preg_match('/[0-9]{1,3}%/', $this->size['watermark']) )
                {
                    $size = $this->size['watermark'] / 100;
                    $this->current_size['watermark']['width'] = $this->current_size['watermark']['width'] * $size;
                    $this->current_size['watermark']['height'] = $this->current_size['watermark']['height'] * $size;
                }
            }
        }
    }

    public function applyWatermark()
    {
        if ( !empty($this->errors) ) return false;

        try {
            $this->getWatermarkPosition();
            if ( !$this->watermark = $this->createImage($this->file['watermark']) )
            {
                throw new Exception('Could not create watermark image');
            }
            if ( !empty($this->size) )
            {
                $this->watermark = $this->resize_png_image($this->watermark, $this->current_size['watermark']['width'], $this->current_size['watermark']['width']);
            }

            if ( !imagecopy($this->image, $this->watermark, $this->position['x'], $this->position['y'], 0, 0, $this->current_size['watermark']['width'], $this->current_size['watermark']['height']) )
            {
                throw new Exception('Could not apply watermark to image');
            }
        }
        catch ( Exception $e )
        {
            $this->error($e);
            return false;
        }
        return true;
    }
    private function getWatermarkPosition()
    {
        $position = $this->position;
        // Horizontal
        if ( preg_match('/right/', $position) ) {
            $x = $this->current_size['image']['width'] - $this->current_size['watermark']['width'] + $this->margin['x'];
        } elseif ( preg_match('/left/', $position) ) {
            $x = 0  + $this->margin['x'];
        } elseif ( preg_match('/center/', $position) ) {
            $x = $this->current_size['image']['width'] / 2 - $this->current_size['watermark']['width'] / 2  + $this->margin['x'];
        }

        // Vertical
        if ( preg_match('/bottom/', $position) ) {
            $y = $this->current_size['image']['height'] - $this->current_size['watermark']['height']  + $this->margin['y'];
        } elseif ( preg_match('/top/', $position) ) {
            $y = 0  + $this->margin['y'];
        } elseif ( preg_match('/center/', $position) ) {
            $y = $this->current_size['image']['height'] / 2 - $this->current_size['watermark']['height'] / 2  + $this->margin['y'];
        }

        if ( !isset($x) || !isset($y) ) {
            throw new Exception('Watermark position has been set wrong');
        }

        $this->position = array('x' => $x,'y' => $y,'string' => $position);
    }

    private function createImage($file)
    {
        $ihandle = fopen($file, 'r');
        $image = fread($ihandle, filesize($file));
        fclose( $ihandle);

        if ( false === ( $img = imagecreatefromstring($image) ) )
        {
            throw new Exception("Image not valid");
        }
        return $img;
    }

    private function resize_png_image($src_image, $width, $height)
    {
        // Get sizes
        if ( !$src_width = imagesx($src_image) )
            throw new Exception('Couldn\'t get image width');

        if ( !$src_height = imagesy($src_image) )
            throw new Exception('couldn\'t get image height');

        // Get percentage and destiny size
        $percentage = (double)$width / $src_width;
        $dest_height = round($src_height * $percentage) + 1;
        $dest_width = round($src_width * $percentage) + 1;

        if ( $dest_height > $height )
        {
            // if the width produces a height bigger than we want, calculate based on height
            $percentage = (double)$height / $src_height;
            $dest_height = round($src_height * $percentage) + 1;
            $dest_width = round($src_width * $percentage) + 1;
        }

        if ( !$dest_image = imagecreatetruecolor($dest_width - 1, $dest_height - 1) )
            throw new Exception('imagecreatetruecolor could not create the image');

        if ( !imagealphablending($dest_image, false) )
            throw new Exception('could not apply imagealphablending');

        if ( !imagesavealpha($dest_image, true) )
            throw new Exception('could not apply imagesavealpha');

        if ( !imagecopyresampled($dest_image, $src_image, 0, 0, 0, 0, $dest_width, $dest_height, $src_width, $src_height) )
            throw new Exception('could not copy resampled image');

        if ( !imagedestroy($src_image) )
            throw new Exception('could not destroy the image');

        return $dest_image;
    }
    public function generate($path = null, $output = null)
    {
        if ( !empty($this->errors) ) return false;

        try
        {
            if ( preg_match('/jp(e)?g/', $this->extension['image']) ) {
                $this->output = 'image/jpeg';
            } elseif ( $this->extension['image'] == 'gif' ) {
                $this->output = 'image/gif';
            } else {
                $this->output = 'image/png';
            }
            if ( is_null($path) ) {
                header('Content-type: ' . $this->output);
            }

            if ($this->extension['image'] == 'gif' || $this->extension['image'] == 'png')
            {
                imagesavealpha($this->image, true);
            }

            // Output / save image
            switch($this->output)
            {
                case 'image/png':
                    $quality = round(abs(($this->quality - 100) / 11.111111));
                    if ( !imagepng($this->image, $path, $quality) ) {
                        throw new Exception('could not generate png output image');
                    }
                    break;
                case 'image/jpeg':
                    if ( !imagejpeg($this->image, $path, $this->quality) ) {
                        throw new Exception('could not generate output jpeg image');
                    }
                    break;
                case 'image/gif':
                    if ( !imagegif($this->image, $path, $this->quality) ) {
                        throw new Exception('could not generate output gif image');
                    }
                    break;
            }

            // Destroy image
            if ( !imagedestroy($this->image) ) {
                throw new Exception('could not destroy image tempfile');
            }
            if ( isset($this->file['watermark']) )
            {
                if ( !imagedestroy($this->watermark) ) {
                    throw new Exception('could not destroy watermark tempfile');
                }
            }
            unset($this->file);
        }
        catch ( Exception $e )
        {
            $this->error($e);
            return false;
        }
        return true;
    }

    public function deleteImage( $moduleName, $delete_image, $requestFile =false) {
        $upload_dir = sfConfig::get('sf_upload_dir');
        $settings = sfConfig::get('app_settings_'.$moduleName.'_0');
        if( $requestFile) {
            $delete = false;
            foreach( $requestFile as $key=>$image)
                if( $image['size'] > 0 && $delete_image[$key])
                    $delete [] = $delete_image[$key];
        }
        else
            $delete = $delete_image;
        if( $delete)
            foreach( $settings as $img)
                foreach( $delete as $del)
                    @unlink( $upload_dir.'/'.$moduleName.'/'.$img['nombre'].'_'.$del);
    }

}