# sharepoint-flysystem

The only sharepoint flysystem that seems to work

## Installation

These instructions are for Laravel, but this application can run standalone as well.
In `composer.json` add

```js

// ...
"repositories": {
  "lubnik2005/sharepoint-flysystem": {
    "type": "vcs",
    "url": "https://github.com/lubnik2005/sharepoint-flysystem.git"
  }
}
// ...
```

Run ` composer require lubnik2005/sharepoint-flysystem`.

### Registration

1. Create driver configs

```php
// config/filesystems.php
return [
    // ...
    'disks' => [
        // ...
            'sharepoint' => [
                'driver' => 'sharepoint',
                'tenantId' => env('SHAREPOINT_TENANT_ID', 'secret'),
                'clientId' => env('SHAREPOINT_CLIENT_ID', 'secret'),
                'clientSecret' => env('SHAREPOINT_CLIENT_SECRET_VALUE', 'secret'),
                'sharepointSite' => env('SHAREPOINT_SITE', 'laravelTest'),
            ]
    ]
// ...
]
```

2. Create the Sharepoint Flysystem Adapter Provider

```php
<?php
// app/Providers/FlySystemSharepointProvider.php

namespace App\Providers;

use Lubnik2005\SharepointFlysystem\Flysystem\FlysystemSharepointAdapter;
use Lubnik2005\SharepointFlysystem\Flysystem\SharepointConnector;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;



class FlySystemSharepointProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register() { }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Storage::extend('sharepoint', function ($app, $config) {

            $connector = new SharepointConnector(
                    $config['tenantId'],
                    $config['clientId'],
                    $config['clientSecret'],
                    $config['sharepointSite'],
            );
            $adapter = new FlysystemSharepointAdapter($connector);

            return new FilesystemAdapter(
                new Filesystem($adapter, $config),
                $adapter,
                $config
            );
        });
    }
}

```

3. Add routes (optional)

   - For `$adapter->getUrl()` to work (for things like Nova Admin File Manager), there needs to be something generating the url.

   ```php
   // ... routes/api.php
   <?php
   // ...
   Route::resource('sharepoint', SharePointController::class);
   // ...

   // ... app/Http/Controllers/SharePointController.php
   ```

   ```php
   <?php
        namespace App\Http\Controllers;
        use Illuminate\Http\Request;
        use Illuminate\Http\Response;
        use Illuminate\Support\Facades\Log;
        use Illuminate\Support\Facades\Storage;
        use Illuminate\Support\Str;
        class ImsFileController extends Controller{
            protected $disk = 'sharepoint';
            /**
             * Display a listing of the resource.
             *
             * @return \Illuminate\Http\Response
             */
            public function index()
            {
                $files = Storage::disk($this->disk)->files();
                return Response($files);
            }
            /**
             * Display the specified resource.
             *
             * @param  int  $id
             * @return \Illuminate\Http\Response
             */
            public function show($id)
            {
                $stream = Storage::disk($this->disk)->readStream($id);

                // Create a temporary file
                $tmpFilePath = sys_get_temp_dir() . '/' . Str::random(16);
                $tmpFile = fopen($tmpFilePath, 'w+b');

                // Write the stream contents to the temporary file
                stream_copy_to_stream($stream, $tmpFile);

                // Be sure to rewind the file pointer before reading
                rewind($tmpFile);

                // Serve the file for download
                $response = new Response(stream_get_contents($tmpFile));

                // Add appropriate headers for download
                $filename = basename($id); // Assuming $id is a path, if not adjust accordingly
                $response->headers->set('Content-Type', 'application/octet-stream');
                $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
                $response->headers->set('Content-Length', filesize($tmpFilePath));

                // Clean up: close and remove the temporary file
                fclose($tmpFile);
                unlink($tmpFilePath);

                return $response;
            }

        }
   ```

## Upcoming

Add a `php artisan publish` command that will auto-install everything for laravel applications, including the adapter and the routes.

Make `sharepoint` name more dynamic and based on the config file (mayb
e).
