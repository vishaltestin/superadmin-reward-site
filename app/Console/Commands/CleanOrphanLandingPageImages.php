<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Models\LandingPageTemplate;
use Carbon\Carbon;

class CleanOrphanLandingPageImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'landing-pages:clean-orphans';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Removes landing page images from storage that are no longer referenced in any page schema.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $disk = Storage::disk('public');
        $directory = 'landing-page-assets';

        // 1. Get all physical files in the directory
        $files = $disk->files($directory);

        if (empty($files)) {
            $this->info('No files found in ' . $directory . '. Everything is clean!');
            return;
        }

        $this->info('Found ' . count($files) . ' files. Checking against database...');

        // 2. Collect all active image filenames from the database
        $activeImages = [];
        
        // Use chunking so we don't run out of memory if you have thousands of templates
        LandingPageTemplate::select('page_schema')->chunk(100, function ($templates) use (&$activeImages) {
            foreach ($templates as $template) {
                if (!$template->page_schema) continue;

                // Convert the schema array to a JSON string so we can easily regex search it
                $schemaString = json_encode($template->page_schema);

                // Find anything that matches our storage path and extract just the filename
                preg_match_all('/landing-page-assets\/([a-zA-Z0-9_\-\.]+\.(?:png|jpg|jpeg|gif|svg|webp))/i', $schemaString, $matches);

                if (!empty($matches[1])) {
                    foreach ($matches[1] as $filename) {
                        $activeImages[] = $filename;
                    }
                }
            }
        });

        // Remove duplicates for a faster search
        $activeImages = array_unique($activeImages);
        $deletedCount = 0;

        // 3. Compare files on disk vs files in the database
        foreach ($files as $file) {
            $filename = basename($file);

            // Give a 24-hour grace period!
            // If someone is currently building a page and just uploaded an image, 
            // it won't be in the DB yet because they haven't clicked save. 
            $lastModified = Carbon::createFromTimestamp($disk->lastModified($file));
            if ($lastModified->diffInHours(now()) < 24) {
                continue; 
            }

            // If the physical file is NOT in our active database list, nuke it.
            if (!in_array($filename, $activeImages)) {
                $disk->delete($file);
                $this->line('Deleted orphan: ' . $filename);
                $deletedCount++;
            }
        }

        $this->info("Cleanup complete. Removed {$deletedCount} orphan image(s).");
    }
}