<?php

namespace MadeByClowd\Nusantara\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use MadeByClowd\Nusantara\Facades\Nusantara;

class NusantaraApiController extends Controller
{
    /**
     * Get all provinces.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function provinces()
    {
        return response()->json(Nusantara::provinces());
    }

    /**
     * Get regencies of a province.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function regencies(Request $request)
    {
        $request->validate([
            'province_id' => 'required|string|size:2',
        ]);

        return response()->json(Nusantara::regenciesOf($request->province_id));
    }

    /**
     * Get districts of a regency.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function districts(Request $request)
    {
        $request->validate([
            'regency_id' => 'required|string|size:4',
        ]);

        return response()->json(Nusantara::districtsOf($request->regency_id));
    }

    /**
     * Get villages of a district.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function villages(Request $request)
    {
        $request->validate([
            'district_id' => 'required|string|size:6',
        ]);

        return response()->json(Nusantara::villagesOf($request->district_id));
    }

    /**
     * Search region names dynamically across all levels.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function search(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:2|max:50',
        ]);

        return response()->json(Nusantara::search($request->q));
    }
}
