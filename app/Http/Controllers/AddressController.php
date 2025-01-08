<?php

namespace App\Http\Controllers;

use App\Models\Address;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    /**
     * Display a listing of the resource.
     */
	public function index() {
		return inertia('Addresses', [
			'addresses' => Address::latest()->get()
		]);
	}

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {
        $request->validate([
			'addressData' => 'required'
		]);

		$newAddress = Address::create([
			'description' => $request->description,
			'addressData' => $request->addressData
		]);

		return back()->with('flash', $newAddress);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Address $address) {
        $address->update([
			'description' => $request->description,
			'addressData' => $request->addressData
		]);

		return back()->with('flash', $address);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Address $address) {
        $address->delete();
		return response(['success' => true]);
    }

	public function getData() {
		return response([
			'addresses' => Address::latest()->get(['id', 'title', 'description', 'addressData'])
		]);
	}
}