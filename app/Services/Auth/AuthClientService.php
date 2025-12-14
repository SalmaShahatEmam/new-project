<?php

namespace App\Services\Auth;

use Throwable;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Http\FormRequest;

class AuthClientService extends AuthAbstract
{
    public function __construct()
    {
        parent::__construct(new User());
    }

    public function deleteAccount(Request $request): JsonResponse
    {
        $user = $request->user();
        DB::beginTransaction();
        $user->tokens()->delete();
        if ($user->delete()) {
            DB::commit();
            return $this->respondWithSuccess(__('Deleted Successfully'));
        }
        DB::rollBack();
        return $this->setStatusCode(400)->respondWithError(__('Failed Operation'));
    }

    public function register(FormRequest $request, $abilities = null)
    {
        $data = $request->except(["_method"]);
        $data['is_active'] = 1;
        $user = User::create($data);
        $user->access_token = $user->createToken('snctumToken', $abilities ?? [], now()->addHours(1))->plainTextToken;
        return $this->handelMobileOTP($user);
    }


    public function providerRegister($request)
    {
        DB::beginTransaction();

        try {
        $user = User::create($request->except([
            "commercial_register",
            "branches_count",
            "brand_name",
            "_method",
            "password_confirmation"
        ]));

        $user->providerDetails()->create($request->only([
            "commercial_register",
            "branches_count",
            "brand_name"
        ]));

        $user->access_token = $user->createToken(
            'sanctumToken',
            [],
            now()->addHours(1)
        )->plainTextToken;

        DB::commit(); 

        return $this->handelMobileOTP($user);

    } catch (Throwable $e) {
        DB::rollBack(); 
        throw $e;
    }
        
    }
}
