<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Models\User;
use Illuminate\Http\Request;
use App\Traits\ApiResponseTrait;
use App\Http\Controllers\Controller;
use App\Enum\ProviderApprovalStatusEnum;

class ProviderRequestController extends Controller
{
        use ApiResponseTrait;

    public function index()
    {

        $users = User::whereHas("providerDetails", function($q){
            $q->where("status", ProviderApprovalStatusEnum::REVIEW);
        })->with("providerDetails")->get();

        return $this->respondWithSuccess(__('Provider requests retrieved successfully'), [
            'providers' => $users,
        ]);
    }


    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:' . implode(',', [ProviderApprovalStatusEnum::ACCEPTED, ProviderApprovalStatusEnum::REJECTED]),
            "rejection_reason" => 'required_if:status,' . ProviderApprovalStatusEnum::REJECTED,
        ]);

        $user = User::findOrFail($id);

        if (!$user->providerDetails) {
            return $this->respondWithError(__('Provider details not found'), [], 404);
        }

        $user->providerDetails->status = $request->status;
        $user->providerDetails->rejection_reason = $request->status === ProviderApprovalStatusEnum::REJECTED ? $request->rejection_reason : null;
        $user->providerDetails->save();

        
        return $this->respondWithSuccess(__('Provider request status updated successfully'), [
            'provider' => $user,
        ]);
    }   
}
