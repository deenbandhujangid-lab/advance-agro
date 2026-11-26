<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function edit()
    {
        $settings = [];
        foreach (array_keys(Setting::DEFAULTS) as $key) {
            $settings[$key] = Setting::get($key);
        }

        return view('settings.edit', ['s' => $settings, 'holidays' => Holiday::orderBy('date')->get()]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'office_name' => 'required|string|max:100',
            'office_lat' => 'required|numeric|between:-90,90',
            'office_lng' => 'required|numeric|between:-180,180',
            'office_radius' => 'required|integer|between:10,1000',
            'grace_minutes' => 'required|integer|between:0,120',
            'grace_days' => 'required|integer|between:0,31',
            'ot_min_minutes' => 'required|integer|between:0,240',
            'ot_days' => 'required|integer|between:1,31',
            'cl_quota' => 'required|integer|between:0,365',
            'sl_quota' => 'required|integer|between:0,365',
        ]);
        foreach ($data as $key => $value) {
            Setting::put($key, $value);
        }

        return back()->with('ok', 'Settings saved.');
    }

    public function storeHoliday(Request $request)
    {
        $data = $request->validate(['date' => 'required|date|unique:holidays,date', 'name' => 'required|string|max:100'],
            ['date.unique' => 'This date is already a holiday.']);
        Holiday::create($data);

        return back()->with('ok', 'Holiday added.');
    }

    public function deleteHoliday(Holiday $holiday)
    {
        $holiday->delete();

        return back()->with('ok', 'Holiday removed.');
    }
}
