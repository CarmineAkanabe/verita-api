<?php

namespace App\Http\Controllers\V1;

use App\DTO\DepartmentData;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\StoreDepartmentRequest;
use App\Http\Requests\V1\UpdateDepartmentRequest;
use App\Http\Resources\V1\DepartmentResource;
use App\Models\Department;
use App\Services\DepartmentService;

class DepartmentController extends Controller
{

    public function __construct(private readonly DepartmentService $service) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return DepartmentResource::collection(Department::all());
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDepartmentRequest $request)
    {
        $department = $this->service->create(DepartmentData::fromRequest($request));
        return (new DepartmentResource($department))->response()->setStatusCode(201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDepartmentRequest $request, Department $department)
    {
        return new DepartmentResource($this->service->update($department, DepartmentData::fromRequest($request)));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Department $department)
    {
        $this->service->delete($department);
        return response()->json(status: 204);
    }
}
