# Installing Intervention Image Library

Intervention Image is a powerful PHP image manipulation library that makes it easy to crop, resize, and optimize images.

## Installation via Composer (Recommended)

If your project uses Composer, install Intervention Image with:

```bash
composer require intervention/image
```

## Manual Installation

If you don't use Composer, you can manually install:

1. Download from: https://github.com/Intervention/image
2. Extract to `vendor/intervention/image/`
3. Include the autoloader in your PHP files

## Requirements

- PHP >= 5.4
- GD Library or ImageMagick extension

## Current Implementation

The `image_upload_auto_handler.php` file will:
1. **First try** to use Intervention Image if available
2. **Fallback** to GD library if Intervention Image is not installed

So the code works even without Intervention Image, but it's recommended for better performance and cleaner code.

## Benefits of Intervention Image

- **Cleaner Code**: One line to crop and resize: `$img->fit(600, 600)`
- **Better Performance**: Optimized algorithms
- **More Features**: Advanced image manipulation options
- **WebP Support**: Built-in WebP format support
- **Multiple Drivers**: Can use GD or ImageMagick

## Example Usage

```php
use Intervention\Image\ImageManagerStatic as Image;

// Load image
$img = Image::make('path/to/image.jpg');

// Fit to 1:1 ratio (center crop + resize)
$img->fit(600, 600);

// Save with compression
$img->save('path/to/output.jpg', 75);
```

## Check Installation

Visit `image_upload_auto.php` and check the "Library Status" section at the bottom of the page to see which libraries are available.

