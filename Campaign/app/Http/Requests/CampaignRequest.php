<?php

namespace App\Http\Requests;

use Illuminate\Validation\Factory as ValidationFactory;
use Illuminate\Foundation\Http\FormRequest;

class CampaignRequest extends FormRequest
{

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = [
            'name' => 'required|max:255',
            'active' => 'boolean|required',
            'banner_id' => 'integer|required',
            'signed_in' => 'boolean|nullable',
            'once_per_session' => 'boolean|required',
            'segments' => 'array',
            'pageview_rules.*.num' => 'required_with:pageview_rules.*.rule',
            'pageview_rules.*.rule' => 'required_with:pageview_rules.*.num',
            'devices.0' => 'required',
            'variants.*.variant' => 'string|required',
            'variants.*.proportion' => 'integer|required',
            'variants.*.control_group' => 'integer|required',
            'variants.*.weight' => 'integer|required',
            'variants.*.banner_id' => 'required_unless:variants.*.control_group,1',
        ];

        return $rules;
    }

    public function all($keys = null)
    {
        $data = parent::all($keys);
        if (isset($data['signed_in'])) {
            $data['signed_in'] = $this->getInputSource()->getBoolean('signed_in');
        }
        $data['active'] = $this->getInputSource()->getBoolean('active', false);
        $data['once_per_session'] = $this->getInputSource()->getBoolean('once_per_session', false);
        return $data;
    }
}
