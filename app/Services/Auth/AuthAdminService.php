<?php

namespace App\Services\Auth;

use App\Enum\UserTypeEnum;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class AuthAdminService extends AuthAbstract
{
    public function __construct()
    {
        parent::__construct(new User());
    }

    /**
     * Login
     */
    public function login(FormRequest $request, $abilities = null)
    {
        $request->authenticate();
        $user = $request->user();

        if ($user->type !== UserTypeEnum::ADMIN && $user->status !== 'ACTIVE') {
            throw ValidationException::withMessages([
                'account' => __('Your account is pending or rejected !'),
            ]);
        }

        $accessToken = $user->createToken('snctumToken', $abilities ?? [], now()->addMonths(3))->plainTextToken;

        if ($this->loginRequireSendOTP) {
            tap($user)->update([
                'email_verified_at' => NULL,
            ])->fresh();
            $user->access_token = $accessToken;
            return $this->handelOTPMethod($user);
        }
        $user->access_token = $accessToken;
        return $user;
    }

    /**
     * forget password.
     *
     * @return JsonResponse
     */
    public function forgetPassword(FormRequest $request, $abilities = null)
    {
        $user = $this->model::whereEmail($request->email)->first();
        return $this->handelOTPMethod($user);
    }

    public function register(FormRequest $request, $abilities = null): User
    {
        $data = $request->validated();
        $user = User::create($data);
        $user->access_token = $user->createToken('snctumToken', $abilities ?? [], now()->addHours(1))->plainTextToken;
        return $this->handelMailOTP($user);
    }
}
