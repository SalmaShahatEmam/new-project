<?php

namespace App\Http\Controllers\Api\V1;

use App\Enum\UserTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Dashboard\CityResource;
use App\Http\Resources\Api\V1\Dashboard\ProjectResource;
use App\Http\Resources\Api\V1\Dashboard\ResourcePriceResource;
use App\Http\Resources\Api\V1\Dashboard\UserResource;
use App\Models\City;
use App\Models\Project;
use App\Models\Resource;
use App\Models\resourcePrice;
use App\Models\User;
use App\Traits\ApiResponseTrait;

class GeneralController extends Controller
{
    use ApiResponseTrait;

    public function citiesIndex()
    {
        $cities = City::where("is_active" , true)->get();

        return $this->respondWithSuccess(__('Cities list'), [
            'cities' => CityResource::collection($cities),
        ]);
    }

    public function home()
    {

        $pendingProjects = Project::where('status', 'PENDING')->get();

        $submittedProjects = Project::where('status', 'submitted')->get();

       $prices = resourcePrice::whereMonth('created_at', now()->month)
    ->whereYear('created_at', now()->year)
    ->latest('created_at')
    ->limit(10)
    ->get();

        return $this->respondWithSuccess(__('Home data'), [
            'pending_projects' => ProjectResource::collection($pendingProjects),
            'submitted_projects' => ProjectResource::collection($submittedProjects),
            'prices' => ResourcePriceResource::collection($prices),
            "pending_users" => UserResource::collection( User::where('type', UserTypeEnum::EMPLOYEE)
                ->where('status', 'PENDING')->get()),
            'pending_project_count' => $pendingProjects->count(),
            'submitted_project_count' => $submittedProjects->count(),
            'total_items' => Resource::count(),
            'total_projects' => Project::count(),
            'active_users' => User::where('type', UserTypeEnum::EMPLOYEE)
                ->where('status', 'ACTIVE')
                ->count(),
            //pending users
               'pending_users_count' => User::where('type', UserTypeEnum::EMPLOYEE)
                ->where('status', 'PENDING')
                ->count(),
        ]);
    }

    public function getTermsAndConditions()
    {
        $data['data'] = collect(setting('terms-and-conditions'))->toArray()[app()->getLocale()] ?? '';
        $data['status'] = 200;
        return $this->setStatusCode(200)->respondWithArray($data);
    }
}
