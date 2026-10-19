<?php

namespace App\Http\Requests\Admin;

class TourStepsRequest extends AdminRequest
{
    use ValidatesTourSteps;

    public function rules(): array
    {
        return ['steps' => ['present', 'array', 'max:50'], ...$this->stepRules()];
    }
}
