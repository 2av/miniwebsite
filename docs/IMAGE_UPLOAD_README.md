# Image Upload with Crop & Zoom Feature

This feature provides a complete image upload solution with cropping, zooming, and automatic optimization to 512x512 pixels.

## Features

- **Image Upload**: Drag and drop or click to upload images
- **Crop Functionality**: Interactive cropping with a square aspect ratio (1:1) for 512x512 output
- **Zoom Controls**: Zoom in/out with slider and buttons
- **Rotation**: Rotate image left or right
- **Flip**: Flip image horizontally or vertically
- **Live Preview**: Real-time preview of the cropped image at 512x512
- **Automatic Optimization**: Images are automatically resized and optimized to exactly 512x512 pixels
- **File Size Display**: Shows original and optimized file sizes

## Files

1. **image_upload_crop.php** - Main HTML page with the upload interface
2. **image_upload_crop.css** - Custom styling for the interface
3. **image_upload_crop.js** - JavaScript functionality for cropping and zooming
4. **image_upload_handler.php** - PHP backend that processes and optimizes images

## Requirements

- PHP 7.0 or higher
- GD Library extension enabled in PHP
- Web server (Apache/Nginx)
- Modern web browser with JavaScript enabled

## Installation

1. All files are already in the `tests/` folder
2. Ensure the `uploaded_images/` directory is writable (will be created automatically)
3. Make sure GD extension is enabled in PHP

## Usage

1. Open `tests/image_upload_crop.php` in your web browser
2. Click "Select Image" or drag and drop an image file
3. Use the cropping tools to adjust the image:
   - Drag the crop box to reposition
   - Resize the crop box by dragging corners
   - Use zoom slider or buttons to zoom in/out
   - Rotate or flip the image as needed
4. Click "Crop & Upload" to process and save the image
5. The image will be automatically optimized to 512x512 pixels

## Supported Formats

- PNG
- JPEG/JPG
- GIF

## File Size Limits

- Maximum upload size: 10MB
- Output size: Exactly 512x512 pixels

## Output

- Optimized images are saved in `tests/uploaded_images/` directory
- Images are saved as PNG format for best quality and transparency support
- Each image gets a unique filename with timestamp

## Technical Details

- Uses **Cropper.js** library for image manipulation
- Uses **Bootstrap 5** for responsive UI
- Uses **Font Awesome** for icons
- PHP GD library for server-side image processing
- Images are resized using high-quality resampling
- PNG compression level: 9 (maximum compression)

## Browser Compatibility

- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)

## Troubleshooting

### GD Library Not Available
If you see an error about GD library:
- Check if GD extension is installed: `php -m | grep gd`
- Enable GD extension in `php.ini`
- Restart your web server

### Upload Directory Permissions
If uploads fail:
- Ensure `uploaded_images/` directory is writable
- Set permissions: `chmod 755 uploaded_images/`

### Image Not Cropping
- Ensure JavaScript is enabled in your browser
- Check browser console for errors
- Verify Cropper.js library is loading correctly

## Notes

- The cropped area maintains a 1:1 aspect ratio (square) to ensure 512x512 output
- Original aspect ratio is preserved during cropping
- Images are automatically optimized for file size while maintaining quality

