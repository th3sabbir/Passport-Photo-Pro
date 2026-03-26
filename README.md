# 📸 Passport Photo Pro

A web-based tool to generate print-ready passport photo sheets from uploaded images. Supports multiple photos, per-photo copy counts, AI background removal, image enhancement, and multi-page PDF export — all on an A4 layout at 300 DPI.

---

## 🚀 Features

- **Multi-photo upload** — Drag and drop or click to select multiple photos at once for batch processing
- **Custom copy counts** — Specify how many copies of each photo you need (1–54) with individual controls per image
- **In-browser cropper** — Crop each photo to the perfect passport aspect ratio with real-time preview
- **AI-powered background removal** — Leveraging remove.bg API for professional, clean backgrounds
- **Smart image enhancement** — AI restoration and sharpening via Cloudinary to improve photo quality
- **Print-ready A4 layout** — Automatic arrangement of photos in grid format at 300 DPI for professional printing
- **Multi-page support** — Seamlessly generates additional pages when photos exceed a single A4 sheet
- **Advanced customization** — Fine-tune photo dimensions, spacing, and border thickness to your exact needs
- **Real-time feedback** — Monitor processing status with detailed progress indicators
- **Responsive design** — Works on desktop and tablet devices for maximum accessibility

---

## 🧰 Tech Stack

| Layer     | Technology                        |
|-----------|-----------------------------------|
| Frontend  | HTML, Tailwind CSS, Vanilla JS    |
| Cropping  | Cropper.js                        |
| Backend   | PHP 7.4+                          |
| Image AI  | remove.bg API, Cloudinary AI      |
| Image Lib | GD Library                        |
| PDF gen   | TCPDF                             |
| Email     | EmailJS                           |

---

## 📦 Prerequisites

- PHP 7.4 or higher (7.4, 8.0, 8.1, 8.2, 8.3)
- Apache/Nginx with mod_rewrite enabled
- Composer (optional, for autoloading)
- A [remove.bg](https://www.remove.bg/api) API key
- A [Cloudinary](https://cloudinary.com/) account (free tier works)

---

## 🛠️ Installation

### 1. Clone the repository

```bash
git clone https://github.com/your-username/passport-photo-pro.git
cd passport-photo-pro
```

### 2. Install dependencies (optional, via Composer)

```bash
composer install
```

### 3. Set up environment variables

Create a `.env` file in the project root:

```env
REMOVE_BG_API_KEY=your_remove_bg_api_key_here
CLOUDINARY_CLOUD_NAME=your_cloud_name
CLOUDINARY_API_KEY=your_cloudinary_api_key
CLOUDINARY_API_SECRET=your_cloudinary_api_secret
```

> ⚠️ Never commit your `.env` file. Add it to `.gitignore`.

### 4. Configure your web server

**Option A: Using Apache**
- Place the project folder in your `htdocs` (XAMPP) or `www` (WAMP) directory
- Access via `http://localhost/passport-photo-pro/` (or your folder name)

**Option B: Using built-in PHP server**
```bash
php -S localhost:8000
```
Then visit `http://localhost:8000`

---

## 📁 Project Structure

```
passport-photo-pro/
├── index.php               # PHP backend — routing & image processing
├── config.php              # Configuration & environment variables
├── composer.json           # PHP dependencies
├── .env                    # Environment variables (not committed)
├── classes/
│   ├── ApiResponse.php     # JSON response handler
│   ├── ImageProcessor.php  # Image processing & PDF generation
│   └── CloudinaryUploader.php  # Cloudinary integration
├── helpers.php             # Utility functions
├── public/
│   ├── index.html          # Frontend UI
│   ├── css/
│   └── js/
└── README.md
```

---

## 📋 System Requirements

Make sure your PHP installation has these extensions enabled:
- **GD Library** — for image manipulation
- **cURL** — for API requests (remove.bg, Cloudinary)
- **fileinfo** — for file type detection

Check with:
```php
php -m | grep -E 'gd|curl|fileinfo'
```

---

## 🖼️ How It Works

### 1. Upload Photos
Open the app in your browser and drag-and-drop your photos onto the upload zone, or click to browse from your device. Multiple images are supported for batch processing.

### 2. Crop (Optional)
Click the **Crop** button on any photo to open the in-browser cropper. The tool automatically locks to the correct passport aspect ratio, so you can't create an improperly formatted image.

### 3. Set Copy Counts
Each photo card displays a **Copies** input field (default: 6). Adjust this per image to control how many times it appears on the final PDF sheet.

### 4. Customize (Optional)
Click **Advanced Options** to adjust:
- **Width & Height** — exact passport photo dimensions in pixels
- **Spacing** — gap between photo rows 
- **Border** — black border thickness around each photo

### 5. Generate PDF
Click **Generate Sheet** to start processing:
- Each photo's background is removed via remove.bg API
- Image quality is enhanced using Cloudinary AI restoration
- Photos are resized, bordered, and arranged on A4 pages (2480×3508 px @ 300 DPI)
- Multiple pages are created automatically if needed

### 6. Download
Once complete, download your print-ready PDF directly to your device

---

## ⚙️ API Endpoints

| Method | Route      | Description                          |
|--------|------------|--------------------------------------|
| GET    | `/`        | Serves the frontend UI               |
| GET    | `/api/status` | Returns system status & API config   |
| GET    | `/api/health` | Health check endpoint                |
| POST   | `/process` | Accepts images, returns a PDF stream |

### `/process` Form Data

| Field       | Type    | Description                              |
|-------------|---------|------------------------------------------|
| `image_0`   | File    | First uploaded image                     |
| `copies_0`  | Integer | Number of copies for image 0             |
| `image_1`   | File    | Second uploaded image (if any)           |
| `copies_1`  | Integer | Number of copies for image 1             |
| `width`     | Integer | Passport photo width in px (default 390) |
| `height`    | Integer | Passport photo height in px (default 480)|
| `spacing`   | Integer | Row spacing in px (default 10)           |
| `border`    | Integer | Border thickness in px (default 2)       |

---

## 🔐 Environment Variables Reference

| Variable                | Description                          |
|-------------------------|--------------------------------------|
| `REMOVE_BG_API_KEY`     | API key from remove.bg               |
| `CLOUDINARY_CLOUD_NAME` | Your Cloudinary cloud name           |
| `CLOUDINARY_API_KEY`    | Cloudinary API key                   |
| `CLOUDINARY_API_SECRET` | Cloudinary API secret                |

---

## 🐛 Known Limitations

- remove.bg has a daily free-tier quota — heavy usage may return a `429` error
- Very large images may slow down processing
- The Cloudinary `gen_restore` transformation may not be available on all plans

---

## ⚡ Quick Start

```bash
# 1. Clone and navigate
git clone https://github.com/your-username/passport-photo-pro.git
cd passport-photo-pro

# 2. Set up environment
cp .env.example .env
# Edit .env with your API credentials

# 3. Run with PHP
php -S localhost:8000

# 4. Open browser
# Visit http://localhost:8000
```

## � Development

### Running tests
```bash
composer test
```

### Starting dev server
```bash
composer start
```

---

## 📬 Feedback & Bug Reports

Use the feedback form in the app to submit bug reports or feature requests.

---

## 🚨 Troubleshooting

### "Endpoint not found" error
- Verify that `.htaccess` is in the project root and properly configured
- Ensure Apache's `mod_rewrite` module is enabled
- Confirm the project folder is accessible at the correct URL path

### "Image processing failed" error
- Validate your remove.bg API key hasn't expired or hit daily quotas
- Double-check your Cloudinary credentials are correct
- Note that remove.bg requires a clearly visible face in the image for background removal
- Check the `/logs/error.log` file for detailed error information

### Missing or outdated PHP extensions
Verify GD, cURL, and fileinfo are loaded:
```bash
php -m | grep -E 'gd|curl|fileinfo'
```

If missing, enable them in your `php.ini`:
```ini
extension=gd
extension=curl
extension=fileinfo
```

### PDF generation issues
- Ensure the `/uploads` and `/cache` directories are writable
- Check available disk space on your server
- Verify PHP's `memory_limit` is set to at least 128MB for large batch processing

---

## 📄 License

MIT License. See `LICENSE` for details.
