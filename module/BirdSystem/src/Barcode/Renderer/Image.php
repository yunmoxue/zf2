<?php


namespace BirdSystem\Barcode\Renderer;


use Zend\Stdlib\ErrorHandler;
use Zend\Barcode\Renderer\Image as Base;

/**
 * Class for extend Image render
 *
 * @codeCoverageIgnore
 */
class Image extends Base
{
    public function outputImage()
    {
        $this->draw();
        $functionName = 'image' . $this->imageType;
        $functionName($this->resource);

        ErrorHandler::start(E_WARNING);
        imagedestroy($this->resource);
        ErrorHandler::stop();
    }

}