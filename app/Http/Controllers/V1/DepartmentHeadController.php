<?php

namespace App\Http\Controllers\V1;

use App\DTO\DepartmentHeadData;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\StoreDepartmentHeadRequest;
use App\Http\Requests\V1\UpdateDepartmentHeadRequest;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use App\Services\DepartmentHeadService;

// use Illuminate\Http\Request;

class DepartmentHeadController extends Controller
{
    public function __construct(private readonly DepartmentHeadService $service) {}
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return UserResource::collection(
            User::where('role', Role::DEPARTMENT_HEAD)->get()
        );
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDepartmentHeadRequest $request)
    {
        $departmentHead = $this->service->create(DepartmentHeadData::fromStoreRequest($request));

        // Reload the model from the DB to pull in defaults like 'presence_status'
        $departmentHead->refresh();

        return (new UserResource($departmentHead))->response()->setStatusCode(201);
    }



    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDepartmentHeadRequest $request, User $departmentHead)
    {
        $departmentHead = $this->service->update($departmentHead, DepartmentHeadData::fromUpdateRequest($request));

        return new UserResource($departmentHead);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $departmentHead)
    {
        $this->service->delete($departmentHead);

        return response()->noContent();
    }
}
