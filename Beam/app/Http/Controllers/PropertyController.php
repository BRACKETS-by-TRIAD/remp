<?php

namespace App\Http\Controllers;

use App\Account;
use App\Property;
use Illuminate\Http\Request;
use Psy\Util\Json;
use Ramsey\Uuid\Uuid;
use Yajra\Datatables\Datatables;

class PropertyController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param Account $account
     * @return \Illuminate\Http\Response
     * @internal param $accountId
     */
    public function index(Account $account)
    {
        return view('properties.index', [
            'account' => $account,
        ]);
    }

    public function json(Account $account, Request $request, Datatables $datatables)
    {
        $properties = $account->properties()->getQuery();
        return $datatables->of($properties)
            ->addColumn('actions', function (Property $property) use ($account) {
                return Json::encode([
                    '_id' => $property->id,
                    'show' => route('accounts.properties.show', [$account, $property]),
                    'edit' => route('accounts.properties.edit', [$account, $property]),
                ]);
            })
            ->rawColumns(['actions'])
            ->setRowId('id')
            ->make(true);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param Account $account
     * @return \Illuminate\Http\Response
     */
    public function create(Account $account)
    {
        return view('properties.create', [
            'account' => $account,
            'property' => new Property(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Account $account
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     * @internal param $accountId
     */
    public function store(Account $account, Request $request)
    {
        $this->validate($request, [
            'name' => 'bail|required|unique:accounts|max:255',
        ]);

        $property = new Property();
        $property->fill($request->all());
        $property->uuid = Uuid::uuid4();
        $property->account()->associate($account);
        $property->save();

        return redirect(route('accounts.properties.index', $account))->with('success', 'Property created');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Property  $property
     * @return \Illuminate\Http\Response
     */
    public function show(Account $account, Property $property)
    {
        return view('properties.show', [
            'property' => $property,
            'snippet' => view('properties._snippet', [
                'property' => $property,
            ]),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param Account $account
     * @param  \App\Property $property
     * @return \Illuminate\Http\Response
     */
    public function edit(Account $account, Property $property)
    {
        return view('properties.edit', [
            'account' => $account,
            'property' => $property,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \App\Property $property
     * @param Account $account
     * @return \Illuminate\Http\Response
     */
    public function update(Account $account, Property $property, Request $request)
    {
        $this->validate($request, [
            'name' => 'bail|required|unique:accounts|max:255',
        ]);

        $property->fill($request->all());
        $property->save();

        return redirect(route('accounts.properties.index', $account))->with('success', 'Property updated');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Property $property
     * @param Account $account
     * @return \Illuminate\Http\Response
     */
    public function destroy(Property $property, Account $account)
    {
        $property->delete();
        return redirect(route('accounts.properties.index', $account))->with('success', 'Property removed');
    }
}
