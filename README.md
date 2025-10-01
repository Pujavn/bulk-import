# Bulk Import + Chunked Image Upload (Laravel 12)

## Quickstart
```bash
cp .env.example .env
php artisan key:generate
composer install
php artisan migrate
php artisan storage:link
php artisan serve


# Bulk Import + Chunked Image Upload (Laravel 12) Minimal steps to run and demo the two flows via the UI: - **CSV Import** (upsert by sku, optional auto-attach images from a folder) - **Manual Image Attach** (chunked/resumable upload, then attach to a product) --- ## Run 1) Configure DB in .env (MySQL), then:
bash
php artisan key:generate
php artisan migrate
php artisan storage:link
php artisan serve


Open:

Importer UI: http://127.0.0.1:8000/import-uploader

Products UI: http://127.0.0.1:8000/products

Use
A) CSV Import (with optional image auto-attach)

Prepare a CSV with headers exactly:

sku,name,price,description,image_filename


Example:

A001,Alpha,10.00,First,demo1.jpg
A002,Beta,12.50,Second,


(Optional) If you want images auto-attached during import, place the referenced files under:

storage/app/import_images/


(e.g. storage/app/import_images/demo1.jpg)

Go to /import-uploader → CSV Import card:

Choose your CSV file.

If using auto-attach, set Images dir to import_images.

Click Import CSV.

You’ll get a summary: Total / Imported / Updated / Invalid / Duplicates (and any file-not-found errors).

B) Manual Image Attach (chunked upload)

Go to /products:

Search/filter (use Only without image to find items missing images).

Click Attach image on a row → you’ll land on /import-uploader?product_id=<ID>.

On /import-uploader:

The Product ID field is prefilled.

Drag & drop an image in the Image Upload card.

The UI shows: Init → Chunks → Complete, then attaches it as the product’s primary image.

Return to /products and reload (optionally with Only without image) to verify.

Notes

Uploaded originals are stored under:

storage/app/public/uploads/originals/...


and are served via /storage/... 

Variants at 256 / 512 / 1024 px are generated automatically (aspect ratio preserved).

CSV import is idempotent/upsert by SKU; duplicate rows are counted and the last occurrence wins.

Troubleshooting

If a page or assets don’t show as expected:

php artisan route:clear
php artisan view:clear
php artisan config:clear
php artisan optimize:clear
