<?php

namespace App\Http\Controllers\Api;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

trait SavesCatalogImages
{
    /** @param Closure(array): Model $save */
    private function saveCatalogImage(array $data, Closure $save): Model
    {
        $image = $data['image'] ?? null;
        unset($data['image']);
        $path = null;

        if ($image) {
            $path = $image->store('catalog-images', 'public');
            abort_if($path === false, 500, 'The image could not be saved.');
            $data['image_url'] = route('catalog-images.show', ['filename' => basename($path)], absolute: false);
        }

        try {
            return DB::transaction(fn (): Model => $save($data));
        } catch (Throwable $failure) {
            if ($path !== null) {
                Storage::disk('public')->delete($path);
            }

            throw $failure;
        }
    }

    public function showCatalogImage(string $filename): StreamedResponse
    {
        $disk = Storage::disk('public');
        $path = 'catalog-images/'.$filename;
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, $filename, [
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
