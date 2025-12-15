<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Enum\UserTypeEnum;
use Illuminate\Http\Request;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Enum\ProviderApprovalStatusEnum;
use App\Services\Auth\AuthClientService;
use App\Http\Resources\Api\Auth\UserResource;
use App\Http\Requests\Api\Auth\SendOTPRequest;
use App\Http\Resources\Api\Auth\ClientResource;
use App\Http\Requests\Api\Auth\VerifyOTPRequest;
use App\Http\Requests\Api\Auth\LoginClientRequest;
use App\Http\Requests\Api\Auth\ChangeMobileRequest;
use App\Http\Requests\Api\Auth\ResetPasswordRequest;
use App\Http\Requests\Api\Auth\ChangePasswordRequest;
use App\Http\Requests\Api\Auth\ForgetPasswordRequest;
use App\Http\Requests\Api\Auth\RegisterClientRequest;
use App\Http\Resources\Api\V1\Client\ProviderResource;
use App\Http\Requests\Api\Auth\ProviderRegisterRequest;
use App\Http\Resources\Api\V1\Client\UserClientResource;
use App\Http\Requests\Api\Auth\ValidateMobileorEmailRequest;
use App\Http\Requests\Api\V1\Client\EditProfile;
use App\Http\Requests\Api\V1\Client\StoreClientEditProfile;
use App\Http\Requests\Api\V1\Client\StoreEditProfile;

/**
 * @group App Client
 * Manage Client App Apis
 *
 * @subGroup Auth
 * @subgroupDescription Auth Cycle Apis
 */
class AuthController extends Controller
{
    use ApiResponseTrait;

    private $authClientService;

    private string $modelResource = ClientResource::class;
    private array $relations = [];

    public function __construct(AuthClientService $authClientService)
    {
        $this->authClientService = $authClientService;
    }

    /**
     * Client Login.
     *
     * an API which Offers a mean to login a client
     * @unauthenticated
     * @header Api-Key xx
     * @header Api-Version v1
     * @header Accept-Language ar
     */
    public function clientLogin(LoginClientRequest $request): JsonResponse
    {
        $user = $this->authClientService->login($request);

        if( $user->type != UserTypeEnum::INDIVIDUAL)
        {
            return $this->errorUnauthorized(__('Unauthorized'));
        }

        return $this->respondWithModelData(
            new UserClientResource(
                $user
            )
        );
    }

    public function storeClientUpdateProfile(StoreClientEditProfile $request): JsonResponse
    {
        $client = $this->authClientService->StoreClientUpdateProfile($request);
        return $this->respondWithModelData(
            new UserClientResource(
                $client
            )
        );

    }
    
    public function individualUpdateProfile(EditProfile $request): JsonResponse
    {
        $client = $this->authClientService->updateProfile($request);

        if ($request->has("image")) {
            uploadImage('client-image', $request->file('image'), $client);
        }

        return $this->respondWithModelData(
            new UserClientResource(
                $client
            )
        );

    }


    public function providerLogin(LoginClientRequest $request): JsonResponse
    {
        $provider = $this->authClientService->login($request);

        if( $provider->type != UserTypeEnum::STORE)
        {
            return $this->errorUnauthorized(__('Unauthorized'));

        }

        if( $provider->providerDetails->status != ProviderApprovalStatusEnum::ACCEPTED)
        {
            return $this->respondWithErrors( __('Provider Not approved Yet') , 416, [
                'code' => [__('Provider Not approved Yet')]
            ]); 

        }
        return $this->respondWithModelData(
              new ProviderResource($provider->load("providerDetails")));
    
    }

    /**
     * Client Register.
     *
     * an API which Offers a mean to register a new client
     * @unauthenticated
     * @header Api-Key xx
     * @header Api-Version v1
     * @header Accept-Language ar
     */
    
    public function registerClient(RegisterClientRequest $request): JsonResponse
    {
        $client = $this->authClientService->register($request);

        if ($request->has("image")) {
            uploadImage('client-image', $request->file('image'), $client);
        }

        return $this->respondWithModelData(
            new UserClientResource(
                $client
            )
        );
    }

    public function storeUpdateProfile(StoreEditProfile $request): JsonResponse
    {
        $provider = $this->authClientService->storeUpdateProfile($request);

        if ($request->has("image")) {
            uploadImage('provider-image', $request->file('image'), $provider);
        }

        return $this->respondWithModelData(
            new ProviderResource($provider->load("providerDetails")));

    }
    public function providerRegister(ProviderRegisterRequest $request)
    {
        $provider = $this->authClientService->providerRegister($request);

        if ($request->has("image")) {
            uploadImage('provider-image', $request->file('image'), $provider);
        }

        return $this->respondWithModelData(
            new ProviderResource($provider->load("providerDetails")));
    }
    /**
     * Send OTP To Mobile Number.
     *
     * an API which Offers a mean to Send OTP To Mobile Number.
     * @unauthenticated
     * @header Api-Key xx
     * @header Api-Version v1
     * @header Accept-Language ar
     */
    public function sendOTP(SendOTPRequest $request): JsonResponse
    {
        return $this->respondWithArray(
            config('global.return_otp_in_response') ? [
                "verification_code" =>
                $this->authClientService->sendOTP($request)->OTP
            ] : []
        );
    }

    /**
     * Re-Send OTP.
     *
     * an API which Offers a mean to Re-Send OTP.
     * @authenticated
     * @header Api-Key xx
     * @header Api-Version v1
     * @header Accept-Language ar
     */
    
    public function resendOTP(Request $request): JsonResponse
    {
        $returned = $this->authClientService->resendOTP($request);
        if (!$returned){
            return $this->setStatusCode(401)->respondWithError(__('Unauthorized'));
        }
        return $this->respondWithArray(
            ['data' => [
                "verification_code" => $this->authClientService->resendOTP($request)->OTP,
            ]]
        );
    }

    /**
     * OTP Verification.
     *
     * an API which Offers a mean to verify user otp
     * @authenticated
     * @header Api-Key xx
     * @header Api-Version v1
     * @header Accept-Language ar
     */
    public function verifyOTP(VerifyOTPRequest $request): JsonResponse
    {
        return $this->authClientService->verifyOTP($request);
    }

    public function mobileVerifyOTP(VerifyOTPRequest $request): JsonResponse
    {
        return $this->authClientService->mobileVerifyOTP($request);
    }
    /**
     * Client New Password.
     *
     * an API which Offers a mean to set new password for logged out clients after verification step.
     * @authenticated
     * @header Api-Key xx
     * @header Api-Version v1
     * @header Accept-Language ar
     */
    public function resetpassword(ResetPasswordRequest $request): JsonResponse
    {
        return $this->authClientService->resetPassword($request);
    }

    /**
     * Client Change Password.
     *
     * an API which Offers a mean to Change password for logged in client.
     * @authenticated
     * @header Api-Key xx
     * @header Api-Version v1
     * @header Accept-Language ar
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        return $this->authClientService->changePassword($request);
    }

    /**
     * Client Forget Password.
     *
     * an API which Offers a mean to reset client password for logged out clients.
     * @unauthenticated
     * @header Api-Key xx
     * @header Api-Version v1
     * @header Accept-Language ar
     */
    public function forgetPassword(ForgetPasswordRequest $request): JsonResponse
    {
        return $this->respondWithModelData(
            new ClientResource(
                $this->authClientService->forgetPassword($request)
            )
        );
    }


    /**
     * Client Change Mobile.
     *
     * an API which Offers a mean to change client mobile number.
     * @authenticated
     * @header Api-Key xx
     * @header Api-Version v1
     * @header Accept-Language ar
     */
    public function changeMobile(ChangeMobileRequest $request): JsonResponse
    {
        return $this->respondWithModelData(
            new ClientResource(
                $this->authClientService->changeMobile($request)
            )
        );
    }


    /**
     * Client Profile.
     *
     * an API which Offers a mean to login a client
     * @authenticated
     * @header Api-Key xx
     * @header Api-Version v1
     * @header Accept-Language ar
     */
    public function profile(Request $request): JsonResponse
    {
        return $this->respondWithModelData(
            new ClientResource(
                $this->authClientService->profile($request)
            )
        );
    }

    /**
     * Client logout.
     *
     * an API which Offers a mean to logout a client
     * @authenticated
     * @header Api-Key xx
     * @header Api-Version v1
     * @header Accept-Language ar
     */
    public function logout(Request $request)
    {
        $this->authClientService->logout($request);
        return $this->respondWithSuccess(
            __('Logged out Successfully')
        );
    }

    /**
     * Client Delete Account.
     *
     * an API which Offers a mean to delete a client account
     * @authenticated
     * @header Api-Key xx
     * @header Api-Version v1
     * @header Accept-Language ar
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        return $this->authClientService->deleteAccount($request);
    }
}
