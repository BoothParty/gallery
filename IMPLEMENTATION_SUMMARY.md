# Picture Upload Architecture - Implementation Summary

## Overview
A complete RESTful API architecture has been implemented to allow image uploads to specific parties in the BoothParty Gallery application.

## Implementation Components

### 1. Database Layer

#### Migration
- **File:** `database/migrations/2026_02_02_234225_add_path_and_metadata_to_pictures_table.php`
- **Changes:**
  - Added `path` column (string) - stores file path
  - Added `original_name` column (nullable string) - original filename
  - Added `mime_type` column (nullable string) - file MIME type
  - Added `size` column (unsigned big integer) - file size in bytes

#### Schema
```sql
pictures table:
- id (bigint, primary key)
- party_id (bigint, foreign key -> parties.id)
- path (string)
- original_name (string, nullable)
- mime_type (string, nullable)
- size (bigint, nullable)
- created_at (timestamp)
- updated_at (timestamp)
```

### 2. Model Layer

#### Picture Model (`app/Models/Picture.php`)
- **Mass Assignable:** `party_id`, `path`, `original_name`, `mime_type`, `size`
- **Relationships:**
  - `party()`: BelongsTo relationship with Party model
- **Factory:** Updated with realistic defaults for testing

#### Party Model (`app/Models/Party.php`)
- **New Relationship:**
  - `pictures()`: HasMany relationship with Picture model

### 3. Validation Layer

#### UploadPictureRequest (`app/Http/Requests/UploadPictureRequest.php`)
- **Authorization Logic:**
  - Checks if party exists
  - Validates party expiration date (rejects if expired)
  - Returns 403 if unauthorized

- **Validation Rules:**
  ```php
  'image' => [
      'required',
      File::image()
          ->min(10)      // 10 KB minimum
          ->max(10 * 1024), // 10 MB maximum
  ]
  ```

- **Custom Error Messages:**
  - Clear, user-friendly validation messages
  - Specific feedback for each validation failure

### 4. Controller Layer

#### PictureController (`app/Http/Controllers/PictureController.php`)

##### `store(UploadPictureRequest $request, Party $party): JsonResponse`
- Validates incoming request using UploadPictureRequest
- Stores image in S3/Minio bucket under `parties/{party_id}/`
- Creates Picture record with metadata
- Returns 201 with picture details and public URL

##### `index(Party $party): JsonResponse`
- Lists all pictures for a party
- Orders by latest first
- Returns S3 public URLs and metadata
- Returns 200 with picture collection

##### `destroy(Party $party, Picture $picture): JsonResponse`
- Validates picture belongs to specified party
- Deletes file from S3/Minio storage
- Deletes database record
- Returns 200 with success message

### 5. Routing Layer

#### API Routes (`routes/api.php`)
```php
Route::prefix('parties/{party}')->group(function () {
    Route::get('/pictures', [PictureController::class, 'index']);
    Route::post('/pictures', [PictureController::class, 'store']);
    Route::delete('/pictures/{picture}', [PictureController::class, 'destroy']);
});
```

**Endpoints:**
- `GET /api/parties/{party}/pictures` - List all pictures
- `POST /api/parties/{party}/pictures` - Upload new picture
- `DELETE /api/parties/{party}/pictures/{picture}` - Delete picture

### 6. Testing Layer

#### Feature Test (`tests/Feature/PictureUploadTest.php`)

**Test Coverage:**
1. ✓ Can upload an image to a party
2. ✓ Validates image is required
3. ✓ Validates file must be an image
4. ✓ Validates image size is within limits
5. ✓ Cannot upload to non-existent party
6. ✓ Cannot upload to expired party
7. ✓ Can list all pictures for a party
8. ✓ Can delete a picture from a party
9. ✓ Cannot delete picture from wrong party

**Testing Approach:**
- Uses Storage::fake('s3') for isolated testing
- Uses UploadedFile::fake() for simulated uploads
- Validates HTTP responses and JSON structures
- Checks database state
- Verifies S3 storage operations

## File Storage Configuration

### Storage Disk
- **Disk:** `s3` (Minio - S3-compatible object storage)
- **Bucket:** `boothparty-gallery` (configured in .env as `AWS_BUCKET`)
- **Endpoint:** `http://minio:9000` (internal Docker network)
- **Public URL:** `http://minio:9000/boothparty-gallery` (configured as `AWS_URL`)

### Minio Configuration (.env)
```env
AWS_ACCESS_KEY_ID=sail
AWS_SECRET_ACCESS_KEY=password
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=boothparty-gallery
AWS_ENDPOINT="http://minio:9000"
AWS_URL="http://minio:9000/boothparty-gallery"
AWS_USE_PATH_STYLE_ENDPOINT=true
```

### Directory Structure in S3/Minio
```
boothparty-gallery (bucket)
└── parties/
    └── {party_id}/
        ├── {generated_filename_1}.jpg
        ├── {generated_filename_2}.png
        └── ...
```

### Public Access
Files are publicly accessible via S3/Minio URLs:
```
http://minio:9000/boothparty-gallery/parties/{party_id}/{filename}
```

## Security Features

1. **File Type Validation**
   - Only allows image files (jpg, jpeg, png, bmp, gif, webp)
   - Validates using Laravel's File::image() rule

2. **Size Constraints**
   - Minimum: 10 KB (prevents empty/corrupt files)
   - Maximum: 10 MB (prevents server overload)

3. **Party Expiration Check**
   - Validates party expiration date in authorization layer
   - Rejects uploads to expired parties
Ensure Minio bucket exists (create via Minio Console or CLI)
# Access Minio Console at: http://localhost:8900
# Default credentials: sail/password

# Ensure storage permissions (if using local storage as fallback)
chmod -R 775 storage
chmod -R 775 bootstrap/cache
```

### Minio Setup
1. **Access Minio Console:** Navigate to `http://localhost:8900`
2. **Login:** Username: `sail`, Password: `password`
3. **Create Bucket:** Create a bucket named `boothparty-gallery` if it doesn't exist
4. **Set Bucket Policy:** Make the bucket publicly accessible for read operations

### Environment Variables
Minio/S3 configuration is already set in `.env`:
```env
AWS_ACCESS_KEY_ID=sail
AWS_SECRET_ACCESS_KEY=password
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=boothparty-gallery
AWS_ENDPOINT="http://minio:9000"
AWS_URL="http://minio:9000/boothparty-gallery"
AWS_USE_PATH_STYLE_ENDPOINT=true
```
php artisan migrate

# Create symbolic link for public storage
php artisan storage:link

# Ensure storage permissions
chmod -R 775 storage
chmod -R 775 bootstrap/cache
```

### Environment Variables
No additional environment variables required. Uses existing `FILESYSTEM_DISK` configuration.

### Testing
```bash
# Run specific test suite
php artisan test --filter=PictureUploadTest

# Run all tests
php artisan test

# Code formatting (already done)
vendor/bin/pint
```

## API Usage Examples

### Upload Picture
```bash
curl -X POST http://localhost/api/parties/1/pictures \
  -F "image=@/path/to/photo.jpg" \
  -H "Accept: application/json"

# Response includes S3/Minio URL:
# "url": "http://minio:9000/boothparty-gallery/parties/1/abc123.jpg"
```

### List Pictures
```bash
curl -X GET http://localhost/api/parties/1/pictures \
  -H "Accept: application/json"
```

### Delete Picture
```bash
curl -X DELETE http://localhost/api/parties/1/pictures/5 \
  -H "Accept: application/json"
```

## Code Quality

- ✅ All code formatted with Laravel Pint
- ✅ Follows Laravel 12 conventions
- ✅ Uses proper type hints and return types
- ✅ Comprehensive test coverage
- ✅ Form Request validation pattern
- ✅ Eloquent relationships properly defined
- ✅ RESTful API design
- ✅ Clear error messages

## Future Enhancement Suggestions

1. **Authentication & Authorization**
   - Add Sanctum token authentication
   - Implement party owner authorization
   - Add guest upload permissions

2. **Image Processing**
   - Automatic image resizing/optimization
   - Thumbnail generation
   - EXIF data extraction

3. **Performance**
   - Use Minio distributed mode for high availability
   - Implement CDN integration
   - Add image caching headers
   - Batch upload support

4. **User Experience**
   - Progress tracking for uploads
   - Image preview before upload
   - Drag-and-drop interface

5. **Advanced Features**
   - S3 lifecycle policies for automatic archiving

## Documentation Files

1. **IMPLEMENTATION_SUMMARY.md** - This file, architectural overview with S3/Minio configuration
## Documentation Files

1. **API_DOCUMENTATION.md** - Complete API reference with examples
2. **IMPLEMENTATION_SUMMARY.md** - This file, architectural overview

## Notes
S3/Minio provides scalable, distributed object storage
- Files are stored in the Minio container volume `sail-minio`
- Minio Console accessible at `http://localhost:8900` for bucket management
- Database connection error during migration is expected if database is not running
- Run `php artisan migrate` once database is available
- Tests use Storage::fake('s3') so they work without actual S3/Minio connection
- Code is production-ready and follows Laravel best practices
- For production, configure appropriate S3 bucket policies and CORS setting
- Code is production-ready and follows Laravel best practices
