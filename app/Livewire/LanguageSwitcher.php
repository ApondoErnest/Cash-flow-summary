<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Support\Locale\AppLocale;
use Livewire\Component;

class LanguageSwitcher extends Component
{
    public function switch(string $locale): void
    {
        if (! AppLocale::isSupported($locale) || AppLocale::resolve() === $locale) {
            return;
        }

        AppLocale::set($locale);

        $this->redirect(
            request()->header('Referer') ?: route('dashboard'),
            navigate: false,
        );
    }

    public function render()
    {
        return view('livewire.language-switcher', [
            'current' => AppLocale::resolve(),
            'supported' => AppLocale::supported(),
        ]);
    }
}
