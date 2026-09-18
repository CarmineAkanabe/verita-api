<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Evidence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EvidenceDownloadController extends Controller
{
    public function show(Request $request, Evidence $evidence)
    {
        $case = $request->user('case-api');

        abort_unless($evidence->case_record_id === $case->id, 404);

        return Storage::disk('local')->response($evidence->file_path);
    }
}
