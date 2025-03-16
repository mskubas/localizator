<?php

namespace Amirami\Localizator\Services;

use Amirami\Localizator\Contracts\Collectable;
use Amirami\Localizator\Contracts\Translatable;
use Amirami\Localizator\Contracts\Writable;
use Amirami\Localizator\Services\Writers\DefaultWriter;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;

class Localizator
{
    private $register = [];
    private $missing = [];

    public function registerKeys(Translatable $keys, string $type)
    {
        $file = $type === 'default' ? 'default.php' : 'json.php';
        $register = [];
        if (file_exists(register_path($file))) {
            $register = require register_path($file);
        }
        $this->register = $register;
        return $this;
    }

    public function saveRegister(Translatable $keys, string $type)
    {
        $register = Arr::except($this->register, array_keys($this->missing));
        $file = $type === 'default' ? 'default.php' : 'json.php';
        $keys = $keys->merge($register)->toArray();
        $this->saveRegisterFile($keys, $file);
    }

    /**
     * @param Translatable $keys
     * @param string $type
     * @param string $locale
     * @return self
     */
    public function localize(Translatable $keys, string $type, string $locale, bool $removeMissing)
    {
        $this->getWriter($type)->put($locale, $this->collect($keys, $type, $locale, $removeMissing));

        return $this;
    }

    protected function saveRegisterFile(array $keys, string $file)
    {
        $writer = new DefaultWriter();
        $contents = $writer->exportArray($keys);
        (new Filesystem)->put(
            register_path($file),
            $contents
        );
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
                    if ($type === 'default') {
                        $dotKeyCollection = Arr::dot($keyCollection);
                    } else {
                        $dotKeyCollection = $keyCollection;
                    }

                    $dotKeyCollection = collect($dotKeyCollection)->filter(function ($item, $key) use ($keys, &$missing) {
                        if (!$keys->has($key) && collect($this->register)->has($key)) {
                            $this->missing[$key] = $item;
                            return false;
                        }
                        return true;     
                    });

                    if ($type === 'default') {
                        $dotKeyCollection = Arr::undot($dotKeyCollection); 
                    }

                    return $dotKeyCollection;
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
