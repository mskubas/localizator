<?php

namespace Amirami\Localizator\Services;

use Amirami\Localizator\Contracts\Collectable;
use Amirami\Localizator\Contracts\Translatable;
use Amirami\Localizator\Contracts\Writable;
use Amirami\Localizator\Services\Writers\DefaultWriter;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class Localizator
{

    public function registerKeys(Translatable $keys, string $type)
    {
        $file = $type === 'default' ? 'default.php' : 'json.php';
        $register = [];
        if (file_exists(register_path($file))) {
            $register = require register_path($file);
        }
        $keys = $keys->merge($register);
        $writer = new DefaultWriter();
        $contents = $writer->exportArray($keys->toArray());
        (new Filesystem)->put(
            register_path($file),
            $contents
        );
        return $this;
    }

    /**
     * @param Translatable $keys
     * @param string $type
     * @param string $locale
     * @return void
     */
    public function localize(Translatable $keys, string $type, string $locale, bool $removeMissing): void
    {
        $this->getWriter($type)->put($locale, $this->collect($keys, $type, $locale, $removeMissing));
    }

    /**
     * @param Translatable $keys
     * @param string $type
     * @param string $locale
     * @return Translatable
     */
    protected function collect(Translatable $keys, string $type, string $locale, bool $removeMissing): Translatable
    {
        return $keys
            ->merge($this->getCollector($type)->getTranslated($locale)
                ->when($removeMissing, function (Translatable $keyCollection) use ($keys, $type) {
                    $file = $type === 'default' ? 'default.php' : 'json.php';
                    $register = require register_path($file);
                    $dotKeyCollection = Arr::dot($keyCollection);
                    $dotKeyCollection = collect($dotKeyCollection)->filter(function ($item, $key) use ($keys, $register, $type) {
                        if (!$keys->has($key) && collect($register)->has($key)) {
                            return false;
                        }
                        return true;
                        
                    });
                    return Arr::undot($dotKeyCollection);
                }))->when(config('localizator.sort'), function (Translatable $keyCollection) {
                    return $keyCollection->sortAlphabetically();
                });
    }

    /**
     * @param string $type
     * @return Writable
     */
    protected function getWriter(string $type): Writable
    {
        return app("localizator.writers.$type");
    }

    /**
     * @param string $type
     * @return Collectable
     */
    protected function getCollector(string $type): Collectable
    {
        return app("localizator.collector.$type");
    }
}
