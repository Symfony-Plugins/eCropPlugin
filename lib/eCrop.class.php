<?php
 	
/**
 * This file is part of the sfCropPlugin package.
 * (c) 2008 Martin Bittner <martin.bittner@leonarddg.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * sfCrop provides a mechanism for croping images.
 *
 * This is taken from sfThumbnail and converted to crop images. 
 * It required the php gd library to be loaded. 
 * Eventually, it will use GDAdapter and ImageMagikAdapter.
 *
 * @package    sfCropPlugin
 * @author     Martin Bittner <martin.bittner@leonarddg.com>
 */
class eCrop
{

	const CROP_CENTER = 1;
	const CROP_TOP = 2;
	const CROP_BOTTOM = 3;
	const CROP_LEFT = 4;
	const CROP_RIGHT = 5;
	
	/**
	 * Width of the thumbnail
	 *
	 * @var unknown_type
	 */
	protected $thumbWidth =  null;
	
	/**
	 * Height of the thumbnail
	 *
	 * @var unknown_type
	 */
	protected $thumbHeight = null;
	
	/**
	 * Mime of the thumbnail
	 * 
	 * The mime of the thumbnail must be one of the allowed types.
	 * See $imgTypes
	 *
	 * @var unknown_type
	 */
	protected $thumbMime = null;

	/**
	 * The X coordinate of the upper left corner
	 * 
	 * The X coordinate of the upper left corner where the cropped 
	 * section will be copied to. Default value is 0.
	 *
	 * @var unknown_type
	 */
	protected $thumbX = 0;
	
	/**
	 * The Y coordinate of the upper left corner
	 * 
	 * The Y coordinate of the upper left corner where the cropped 
	 * section will be copied to. Default value is 0.
	 *
	 * @var unknown_type
	 */
	protected $thumbY = 0;
	
	/**
	 * Width of the source image
	 *
	 * @var unknown_type
	 */
	protected $sourceWidth;
	
	/**
	 * Height of the source image
	 *
	 * @var unknown_type
	 */
	protected $sourceHeight;
	
	/**
	 * The width of the section to crop
	 *
	 * @var unknown_type
	 */
	protected $cropWidth;
	
	/**
	 * The height of the section to crop
	 *
	 * @var unknown_type
	 */
	protected $cropHeight;

	/**
	 * The mime of the original picture.
	 * 
	 * The mime of the original picture must be one of the supported
	 * types. See $imgTypes.
	 *
	 * @var unknown_type
	 */
	protected $sourceMime;
	
	/**
	 * The X coordinate of the crop upper left corner.
	 * 
	 * The X coordinate of the upper left corner of the section
	 * to be cropped.
	 *
	 * @var unknown_type
	 */
	protected $cropX;
	
	/**
	 * The Y coordinate of the crop upper left corner.
	 * 
	 * The Y coordinate of the upper left corner of the section
	 * to be cropped.
	 *
	 * @var unknown_type
	 */	
	protected $cropY;
	
	/**
	 * GD Image Resource for the source image.
	 *
	 * @var unknown_type
	 */
	protected $sourceImageResource;
	
	/**
	 * GD Image Resource for the thumbnail image.
	 *
	 * @var unknown_type
	 */
	protected $thumbImageResource;
	
	/**
	 * Path for the temporary file needed if opening from http(s)
	 *
	 * @var unknown_type
	 */
	protected $tempFile = null;
	
	/**
	 * JPEG Qualiry
	 *
	 * @var unknown_type
	 */
	protected $jpgQuality = 80;
	
	/**
	 * List of accepted image types based on MIME
	 * descriptions that this adapter supports
	 */
	protected $imgTypes = array(
		'image/jpeg',
		'image/pjpeg',
		'image/png',
		'image/gif',
	);

	/**
 	 * Stores function names for each image type.
	 */
	protected $imgLoaders = array(
		'image/jpeg'  => 'imagecreatefromjpeg',
		'image/pjpeg' => 'imagecreatefromjpeg',
		'image/png'   => 'imagecreatefrompng',
		'image/gif'   => 'imagecreatefromgif',
	);
 	
	/**
	 * Stores function names for each image type.
	 */
	protected $imgCreators = array(
		'image/jpeg'  => 'imagejpeg',
		'image/pjpeg' => 'imagejpeg',
		'image/png'   => 'imagepng',
		'image/gif'   => 'imagegif',
	);
	
	
	/**
	 * Class Constructor.
	 *
	 * @param string $sourceImage	Path to the source image. Can be on the web (http/https).
	 * @param int $cropX			X Coordinate of the upper left corner of the area to crop
	 * @param int $cropY			Y Coordinate of the upper left corner of the area to crop
	 * @param int $cropWidth		Wdith of the area to crop
	 * @param int $cropHeight		Height of the area to crop
	 */
	public function __construct($sourceImage, $cropX = 0, $cropY = 0, $cropWidth = 1, $cropHeight = 1)
	{
		$this->loadFile($sourceImage);
		
		$this->setCropX($cropX);
		$this->setCropY($cropY);
		$this->setCropWidth($cropWidth);
		$this->setCropHeight($cropHeight);
	}
	
	/**
	 * Loads an image from a file or URL and creates an internal thumbnail out of it
	 *
	 * @param string $image 	filename (with absolute path) of the image to load. 
	 *							If the filename is a http(s) URL, then an attempt to download the file will be made.
	 *
	 * @return boolean 		True if the image was properly loaded
	 * @throws Exception 	If the image cannot be loaded, or if its mime type is not supported
	 */
	private function loadFile($image)
	{
	  if (eregi('http(s)?://', $image))
	  {
	    if (class_exists('sfWebBrowser'))
	    {
	      if (!is_null($this->tempFile)) {
	        unlink($this->tempFile);
	      }
	      $this->tempFile = tempnam('/tmp', 'sfCropPlugin');

	      $b = new sfWebBrowser();
	      try
	      {
	        $b->get($image);
	        if ($b->getResponseCode() != 200) {
	          throw new Exception(sprintf('%s returned error code %s', $image, $b->getResponseCode()));
	        }
	        file_put_contents($this->tempFile, $b->getResponseText());
	        if (!filesize($this->tempFile)) {
	          throw new Exception('downloaded file is empty');
	        } else {
	          $image = $this->tempFile;
	        }
	      }
	      catch (Exception $e)
	      {
	        throw new Exception("Source image is a URL but it cannot be used because ". $e->getMessage());
	       }
	     }
	     else
	     {
	       throw new Exception("Source image is a URL but sfWebBrowserPlugin is not installed");
	     }
	   }
	   else
	   {
	     if (!is_readable($image))
	     {
	       throw new Exception(sprintf('The file "%s" is not readable.', $image));
	     }
	   }
	
	   $this->sourceImage = $image;
	}
	
	/**
	 * Create GD Image Resource from the source image.
	 *
	 */	
	private function createSourceImageResource()
	{
		// Only do it once.
		if (!is_resource($this->sourceImageResource))
		{
		  $imgData = @GetImageSize($this->sourceImage);

		  if (!$imgData)
		  {
		    throw new Exception(sprintf('Could not load image %s', $image));
		  }

		  $this->sourceWidth = $imgData[0];
		  $this->sourceHeight = $imgData[1];

		  if (in_array($imgData['mime'], $this->imgTypes))
		  {
			$this->sourceMime = $imgData['mime'];	
			
		    $loader = $this->imgLoaders[$imgData['mime']];
		    if(!function_exists($loader))
		    {
		      throw new Exception(sprintf('Function %s not available. Please enable the GD extension.', $loader));
		    }

		    $this->sourceImageResource = $loader($this->sourceImage);	
			if (!$this->sourceImageResource)
				throw new Exception(sprintf("Can't create source image ressource using '%s' function.", $loader));	
		  } 
		  else
		  {
			throw new Exception(sprintf('Image MIME type %s not supported', $imgData['mime']));
		  }
		}
	}
	
	/**
	 * Create GD Images Resources for the thumbnail.
	 *
	 */	
	private function createThumbImageResource()
	{
		// Free the resource if already created
		$this->freeThumb();
			
	    $this->thumbImageResource = imagecreatetruecolor($this->getThumbWidth(), $this->getThumbHeight());	
		if (!$this->thumbImageResource)
			throw new Exception("Can't create source image ressource using '%s' function.", $loader);		
	}
	
	/**
	 * Create GD Images Resources.
	 *
	 */
	private function createImagesResources()
	{
		$this->createSourceImageResource();
		$this->createThumbImageResource();				
	}
		
	/**
	 * Crop the image and save it.
	 *
	 * @param string $thumbDest		path to the thumbnail file
	 * @param boolean $free			flag to free all resource after the croping took place
	 */	
	public function crop($thumbDest, $free = true)
	{
		$this->createImagesResources();

		imagecopyresampled(
			$this->thumbImageResource, 
			$this->sourceImageResource, 
			$this->getThumbX(), 
			$this->getThumbY(), 
			$this->getCropX(), 
			$this->getCropY(),
			$this->getThumbWidth(),
			$this->getThumbHeight(),
			$this->getCropWidth(),
			$this->getCropHeight()
		);

	   $creator = $this->imgCreators[$this->getThumbMime()];
	   if(!function_exists($creator))
	   {
	     throw new Exception(sprintf('Function %s not available. Please enable the GD extension.', $creator));
	   }
	
	   if ($creator == 'imagejpeg')
	   {
	     $creator($this->thumbImageResource, $thumbDest, $this->jpgQuality);
	   }
	   else
	   {
	     $creator($this->thumbImageResource, $thumbDest);
	   }
	
		if ($free)
			$this->freeAll();
		else
			$this->freeThumb();
		
	}
	
	/**
	 * Crop the largest possible square of the source image.
	 *
	 * Will compute the largest possible square to crop. It also let you choose which
	 * part will be cropped for a tall image, top, center, bottom or for a wide
	 * image, it will let you choose from left, center, right.
	 *
	 * @throw Exception
	 *
	 * @param string $thumbDest			Path to the thumbnail
	 * @param const $whereToCropTall	Where to crop if the image is vertical
	 * @param const $whereToCropWide	Where to crop if the image is horizontal
	 */
	public function cropLargestSquareArea($thumbDest, $whereToCropTall, $whereToCropWide, $free = true)
	{
		$this->cropLargestPossibleArea($thumbDest, $whereToCropTall, $whereToCropWide, 1, $free);
	}

	/**
	 * Crop the largest possible square of the source image.
	 *
	 * Will compute the largest possible square to crop. It also let you choose which
	 * part will be cropped for a tall image, top, center, bottom or for a wide
	 * image, it will let you choose from left, center, right.
	 *
	 * @throw Exception
	 *
	 * @param string $thumbDest			Path to the thumbnail
	 * @param const $whereToCropTall	Where to crop if the image is vertical
	 * @param const $whereToCropWide	Where to crop if the image is horizontal
	 * @param int $ratio				Ratio (width / height) of area to crop. 1 crops a square.
	 */
	public function cropLargestPossibleArea($thumbDest, $whereToCropTall, $whereToCropWide, $ratio = 1, $free = true)
	{
		// Get the dimensions of the source image loaded.
		$this->createSourceImageResource();
		
		// Determine if the original picture is vertical (tall) or horizontal (not tall)
		$isSourceTall = ($this->sourceWidth < $this->sourceHeight) ? true : false;	
		
		// Determine the width and height of the crop
		if ($ratio == 1) // It's a square
		{
			$cropWidth = $cropHeight = ($this->sourceWidth < $this->sourceHeight) ? $this->sourceWidth : $this->sourceHeight;					
		} 
		elseif ($ratio > 1) // is a horizontal crop
		{
			$cropWidth = $this->sourceWidth;
			$cropHeight = $this->sourceWidth / $ratio;
		}
		else // is a vertical crop
		{
			$cropHeight = $this->sourceHeight;
			$cropWidth = $this->sourceHeight * $ratio;
		}

		// Set the calculated crop size
		$this->setCropSize($cropWidth, $cropHeight);
		
		if ($isSourceTall)
		{
			// Calculate (x,y) coordinates
			switch($whereToCropTall)
			{
				case self::CROP_TOP:
					$this->setCropCoords(0, 0);					
					break;
				case self::CROP_BOTTOM:
					$this->setCropCoords(0, $this->sourceHeight - $this->getCropHeight());
					break;	
				case self::CROP_CENTER:
					$this->setCropCoords(0, (($this->sourceHeight - $this->getCropHeight()) /  2));
					break;		
				default:						
					throw new Exception(sprintf("Param 'whereToCropTall' is invalid (%s).", $whereToCropTall));
			}
		} 
		else 
		{
			// Calculate (x,y) coordinates
			switch($whereToCropWide)
			{
				case self::CROP_LEFT:
					$this->setCropCoords(0, 0);					
					break;
				case self::CROP_RIGHT:
					$this->setCropCoords($this->sourceWidth - $this->getCropWidth(), 0);
					break;	
				case self::CROP_CENTER:
					$this->setCropCoords((($this->sourceWidth - $this->getCropWidth()) / 2), 0);
					break;		
				default:
					throw new Exception(sprintf("Param 'whereToCropWide' is invalid."));
			}			
		}		

		$this->crop($thumbDest, $free);		
	}

	/**
	 * Get the width of the thumbnail.
	 *
	 * Return the width of the thumbnail. If null, it will return the width of the crop zone.
	 *
	 * @return int	The width of the thumbnail.
	 */
	public function getThumbWidth()
	{
		return (!is_null($this->thumbWidth)) ? $this->thumbWidth : $this->getCropWidth();
	}
	
	/**
	 * Set the Width of the thumbnail.
	 * 
	 * Set the width of the thumbnail. The constructor initialize this 
	 * the same width of the zone to be cropped from the source image so that
	 * by default the thumbnail is the same size of the cropped zone.
	 * 
	 * @param int $value	Width of the thumbnail
	 */
	public function setThumbWidth($value)
	{
		if ($value <= 0)
			throw new Exception('Thumbnail width must be greater than 0.');
					
		$this->thumbWidth = $value;
	}
	
	/**
	 * Get the height of the thumbnail.
	 *
	 * Return the height of the thumbnail. If null, it will return the height of the crop zone.
	 *
	 * @return int 	The height of the thumbnail.
	 */	
	public function getThumbHeight()
	{
		return (!is_null($this->thumbHeight)) ? $this->thumbHeight : $this->getCropHeight();
	}
	
	/**
	 * Set the height of the thumbnail.
	 * 
	 * Set the height of the thumbnail. The constructor initialize this 
	 * the same height of the zone to be cropped from the source image so that
	 * by default the thumbnail is the same size of the cropped zone.
	 *
	 * @param int $value	Height of the thumbnail
	 */
	public function setThumbHeight($value)
	{
		if ($value <= 0)
			throw new Exception('Thumbnail height must be greater than 0.');
			
		$this->thumbHeight = $value;
	}

	/**
	 * Set the width and the height of the thumbnail.
	 *
	 * @param int $width		Width of the thumbnail
	 * @param int $height		Height of the thumbnail
	 */
	public function setThumbSize($width, $height)
	{
		$this->setThumbWidth($width);
		$this->setThumbHeight($height);
	}
	
	/**
	 * Compute the thumb width/height as a percentage of source image's dimension.
	 * 
	 * Calling setThumbSizeRelative(50) would create a thumbnail half the width/height
	 * of the original image. Calling setThumbSizeRelative(200) would create a thumbnail
	 * twice the size of the original image.
	 * 
	 * In order to do that, we need to create image resource (needed anyways to perform
	 * the actual crop operation).
	 *
	 * @throw Exception
	 * @param int $percentage	Percentage to use in the computation
	 */
	public function setThumbSizeRelative($percentage)
	{
		if ($percentage <= 0)
			throw new Exception('Percentage must be greater than 0.');
			
		$percentage = $percentage / 100;
		
		$this->createImagesResources();
		
		$this->setThumbWidth($percentage * $this->getCropWidth());
		$this->setThumbHeight($percentage * $this->getCropHeight());
		
	}
	
	/**
	 * Get the mime type of the thumbnail.
	 *
	 * Return the mime type of the thumbnail. 
	 * If it is not set yet, it return the mime-type of the source image
	 * so that the thumbnail is of the same type.
	 * 	
	 * @return string	Return the mime type of the thumbnail.
	 */		
	public function getThumbMime()
	{
		return (!is_null($this->thumbMime)) ? $this->thumbMime : $this->getSourceMime();
	}
	
	/**
	 * Set the mime type of the thumbnail.
	 * 
	 * Set the mime of the thumbnail. Must be one of the
	 * allowed types. See $imgTypes
	 *
	 * @param string $mime
	 */
	public function setThumbMime($mime)
	{
		if (!in_array(strtolower($mime), $this->imgTypes))
			throw new Exception('The mime type is not one of the supported types.');
			
		$this->thumbMime = $mime;
	}
	
	/**
	 * Get the X coordinate of the upper left corner of where to put the cropped image.
	 *
	 * Default value is 0.
	 *	
	 * @return int	The X coordinate of the upper left corner of where to put the cropped image.
	 */	
	public function getThumbX()
	{
		return $this->thumbX;
	}

/**
	 * Set the X coordinate of the upper left corner of where to put the cropped image.
	 *
	 * Set the X coordinate of the upper left corner of 
	 * where to put the cropped image in the thumbnail.
	 * Default value is 0.	
	 * 
	 * @throw Exception
	 * @param int $x 	X coordinate
	 */	
	public function setThumbX($x)
	{
		if ($x < 0)
			throw new Exception('The X coordinate of the upper left corner of the zone where the cropped image will be "pasted" must be equal or greater than 0.');

		$this->thumbX = $x;
	}

	/**
	 * Get the Y coordinate of the upper left corner of where to put the cropped image.
	 *
	 * Default value is 0.	
	 *
	 * @return int	The Y coordinate of the upper left corner of where to put the cropped image in the thumbnail.
	 */	
	public function getThumbY()
	{
		return $this->thumbY;
	}

	/**
	 * Set the Y coordinate of the upper left corner of where to put the cropped image.
	 *
	 * Set the Y coordinate of the upper left corner of where to put the cropped image in the thumbnail.
	 * Default value is 0.
	 * 
	 * @throw Exception
	 * @param int $y	Y Coordinate
	 */
	public function setThumbY($y)
	{
		if ($y < 0)
			throw new Exception('The Y coordinate of the upper left corner of the zone where the cropped image will be "pasted" must be equal or greater than 0.');

		$this->thumbY = $y;
	}
		
	/**
	 * Set the width and the height of the crop zone.
	 *
	 * @param int $width		Width of the crop zone
	 * @param int $height		Height of the crop zone
	 */		
	public function setCropSize($width, $height)
	{
		$this->setCropWidth($width);
		$this->setCropHeight($height);
	}
	
	/**
	 * Get the width of the zone to crop.
	 *
	 * @return int	Width of the zone to crop.
	 */
	public function getCropWidth()
	{
		return $this->cropWidth;
	}
	
	/**
	 * Set the width of the zone to crop.
	 * 
	 * @throw Exception
	 * @param int $width	Width of the zone to crop
	 */	
	public function setCropWidth($width)
	{
		if ($width <= 0)
			throw new Exception('The width of the zone to crop must be greater than 0.');
					
		$this->cropWidth = $width;
	}
	
	/**
	 * Get the height of the zone to crop of the source image.
	 *
	 * @return int	Height of the zone to crop of the source image.
	 */	
	public function getCropHeight()
	{
		return $this->cropHeight;
	}
	
	/**
	 * Set the height of the zone to crop.
	 *
	 * @throw Exception
	 * @param int $height	Height of the zone to crop
	 */	
	public function setCropHeight($height)
	{
		if ($height <= 0)
			throw new Exception('The height of the zone to crop must be greater than 0.');
			
		$this->cropHeight = $height;
	}
	
	/**
	 * Get the mime type of the source image.
	 *
	 * @return string	The mime-type of the source image.
	 */		
	public function getSourceMime()
	{
		return $this->sourceMime;
	}
	
	/**
	 * Set the coordinates of the upper left corner of the zone to crop.
	 *
	 * Set the coordinates of the upper left corner of the zone to crop from the source image.
	 * 
	 * @throw Exception
	 * @param int $x	X coordinate.
	 * @param int $y	Y coordinate.	
	 */	
	public function setCropCoords($x, $y)
	{
		$this->setCropX($x);
		$this->setCropY($y);
	}
	
	/**
	 * Get the X coordinate of the upper left corner of the zone to crop.
	 *
	 * @return int	The X coordinate of the upper left corner of the zone to crop.
	 */	
	public function getCropX()
	{
		return $this->cropX;
	}
	
	/**
	 * Set the X coordinate of the upper left corner of the zone to crop.
	 *
	 * Set the X coordinate of the upper left corner of the zone to crop from the source image.
	 * 
	 * @throw Exception
	 * @param int $x	X coordinate.
	 */	
	public function setCropX($x)
	{
		if ($x < 0)
			throw new Exception('The X coordinate of the upper left corner of the zone to crop must be equal or greater than 0.');
		
		$this->cropX = $x;
	}
	
	/**
	 * Get the Y coordinate of the upper left corner of the zone to crop.
	 *
	 * @return int	The Y coordinate of the upper left corner of the zone to crop from the source image.
	 */	
	public function getCropY()
	{
		return $this->cropY;
	}
	
	/**
	 * Set the Y coordinate of the upper left corner of the zone to crop.
	 *
	 * Set the Y coordinate of the upper left corner of the zone to crop from the source image.
	 * 
	 * @throw Exception
	 * @param int $y	Y coordinate
	 */
	public function setCropY($y)
	{
		if ($y < 0)
			throw new Exception('The Y coordinate of the upper left corner of the zone to crop must be equal or greater than 0.');
		
		$this->cropY = $y;
	}
	
	/**
	 * Get the Jpeg Quality.
	 *
	 * Default value is set to 80.
	 * 
	 * @return int	Quality for the Jpeg
	 */
	public function getCropYality()
	{
		return $this->jpgQuality;
	}
	
	/**
	 * Set the Jpeg Quality.
	 *
	 * @throw Exception
	 * @param int $quality		Jpeg Quality
	 */
	public function setJpgQuality($quality)
	{
		if ($quality <= 0)
			throw new Exception('Jpeg Quality must be greater than 0.');
			
		$this->jpgQuality = $quality;
	}
	/**
	 * Free resource for the source image.
	 *
	 */
	public function freeSource()
	{
		if (is_resource($this->sourceImageResource))
			imagedestroy($this->sourceImageResource);
	}
	
	/**
	 * Free resource for the thumbnail image.
	 *
	 */
	public function freeThumb()
	{
		if (is_resource($this->thumbImageResource))
			imagedestroy($this->thumbImageResource);		
	}
	
	/**
	 * Free all resources
	 *
	 */
	public function freeAll()
	{
		$this->freeSource();
		$this->freeThumb();
	}
}