<?php

namespace App\View\Components;

use Illuminate\View\Component;

class Icon extends Component
{
    public string $path;

    public function __construct(public string $name, public int $size = 16)
    {
        $this->path = config("icons.$name", config('icons.doc', ''));
    }

    public function render()
    {
        return view('components.icon');
    }
}
