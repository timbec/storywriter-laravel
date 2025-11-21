<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreStoryRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateStoryRequest;
use App\Models\Story;

class StoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // return StoryResource::collection(Story::all());
        return Story::all()->toResourceCollection(); 
    }


    public function sdkCredentials(Request $request)
    {
        // validate incoming data
        $request->validate([
            'agentId' => 'required|string'
        ]);

        // example return structure (must match what RN expects)
        return response()->json([
            'sessionId' => 'xyz123',
            'apiKey'    => env('ELEVENLABS_API_KEY'),
            'agentId'   => $request->agentId,
            'expiresAt' => now()->addMinutes(15)->toISOString(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreStoryRequest $request)
    {
        $story = Story::create($request-validated());

        return $story->toResource();
    }

    /**
     * Display the specified resource.
     */
    public function show(Story $story)
    {
        //return new StoryResource($story); 

        //return StoryResource::make($story); 
        //Laravel 12
        return $story->toResource();
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateStoryRequest $request, Story $story)
    {
        $story->update($request->validated()); 

        return $story->toResource(); 
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Story $story)
    {
        //
    }
}
