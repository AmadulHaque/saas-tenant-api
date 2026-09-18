<?php

namespace App\Http\Requests;

use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @method mixed user(string|null $guard = null)
 */
class CheckoutRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('api') !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'plan_id' => [
                'required',
                'integer',
                Rule::exists('subscription_plans', 'id')->where(
                    fn ($query) => $query->where('is_active', true)
                ),
            ],
        ];
    }

    /**
     * The active plan being purchased.
     */
    public function plan(): SubscriptionPlan
    {
        /** @var SubscriptionPlan $plan */
        $plan = SubscriptionPlan::query()->findOrFail($this->integer('plan_id'));

        return $plan;
    }
}
