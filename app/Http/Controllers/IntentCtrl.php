<?php

namespace App\Http\Controllers;

use App\Api\NLP\NLPAPI;
use App\Handlers\ChatHandler;
use App\Handlers\ConservationHandler;
use App\Handlers\StatsHandler;
use App\Helpers\Utility;
use App\Models\Intents;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class IntentCtrl extends Controller
{
    //Validators
    public $createValidator = [
        'name' => 'bail|required|unique:intents',
        'type' => 'bail|required|integer',
        'define_words' => 'bail|required',
        'output' => 'bail|required',
    ];

    // CRUD Functions

    public function create(Request $request)
    {
        //dd($request->all());
        $validator = Validator::make($request->all(),$this->createValidator);
        if ($validator->fails()) {
            $error = Utility::formatErrorsArray($validator);
            return response()->json($error);
        }

        $intent = new Intents;
        $intent->name = trim($request->name);
        $intent->type = (integer) trim($request->type);

        $api = collect((new NLPAPI)
            ->getNlpWords(strtolower(trim($request->define_words)))->json_response);

        $processed_message = [];
        foreach ($api['Keywords'] as $word){
            $processed_message[] = ($word->KeywordRoot);
        }

        $intent->question = $request->define_words;
        $intent->define_words = $processed_message;
        $intent->output = trim($request->output);

        //Variables
        if ((boolean) $request->has_variable === true){
            $intent->has_variable = (boolean) trim($request->has_variable);
            $intent->variable_names = $request->variable_names;
            $intent->variable_questions = $request->variable_questions;

            $cvv = \count($request->variable_names);
            for ($i=0;$i<$cvv;$i++){
                $VV [] = null;
            }
            $intent->variable_values = $VV;
        }

        //Operations
        if ((boolean) $request->has_operation === true){
            $intent->has_operation = (boolean) trim($request->has_operation);
            $intent->operation_type = strtoupper(trim($request->operation_type));
            $intent->operation_url = trim($request->operation_url);
        }

        //Forward
        if((boolean) $request->forward === true){
            $intent->forward = (boolean) trim($request->forward);
            $intent->forwardID = trim($request->forwardID);
        }

        //dd($intent);
        $intent->save();
        $intent->id = $intent->_id;
        $intent->save();
        return response()->json($intent);
    }

    public function read(){
        $intents = Intents::all();

        return response()->json($intents);
    }

    public function readSingle($id)
    {
        $intent = Intents::find($id);

        return response()->json($intent);
    }

    public function update($id,Request $request)
    {
        $intent = Intents::find($id);

        if($request->name){
            $intent->name = $request->name;
        }

        if($request->type){
            $intent->type = $request->type;
        }



        if($request->define_words){
            $api = collect((new NLPAPI)
                ->getNlpWords(strtolower(trim($request->define_words)))->json_response);

            $processed_message = [];
            foreach ($api['Keywords'] as $word){
                $processed_message[] = ($word->KeywordRoot);
            }

            $intent->define_words = $processed_message;
        }


        if($request->has_operation){
            $intent->has_operation = $request->has_operation;
        }

        if($request->operation_type){
            $intent->operation_type = $request->operation_type;
        }

        if($request->operation_url){
            $intent->operation_url = $request->operation_url;
        }

        if($request->output){
            $intent->output = $request->output;
        }

        $intent->save();
        return response()->json($intent);
    }

    public function delete($id)
    {
        $intent = Intents::find($id);
        $intent->delete();

        return response()->json($intent);
    }

    /**
     * @param $id
     * @param $value_order
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */

    public function insertValue($id, $value_order, Request $request)
    {
        $intent = Intents::find($id);
        $next = $value_order;
        $VV = [];
        foreach ($intent->variable_values as $value){
            $VV [] = $value;
        }
        $VV[$value_order] = trim($request->message);
        $intent->variable_values = $VV;
        $intent->save();
        $answer = collect($intent);

        ++$next;
        if ($intent['has_operation'] === true && $next == \count($answer['variable_questions'])){
            $ans = ChatHandler::intentHasOperation($intent);
            $answer = [
                'answer' => $ans->original,
                'exit_order' => $value_order
            ];
            return response()->json($answer);
        }

        if($next == \count($answer['variable_questions'])){
            $out = ConservationHandler::changeOutputWithVariable($intent);
            return response()->json($out);
        }

        $ans = [
            'answer' => $answer['variable_questions'][$next],
            'exit_order' => $value_order
        ];

        return response()->json($ans);

    }

    public function increaseSeenC()
    {
        (new StatsHandler())->increaseSeenCount();
    }
}
