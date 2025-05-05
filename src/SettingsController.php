<?php
namespace AiTranslator;

use Statamic\Http\Controllers\CP\CpController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Arr;
use Statamic\Facades\Blueprint;
use Statamic\Facades\YAML;
use Statamic\Fields\Blueprint as BlueprintContract;



class SettingsController extends CpController
{
    public function index()
    {
        $user = Auth::user();
        if (!$user) {
            return redirect('/');
        } elseif (!$user->super) {
            return redirect('/');
        }
    
        $blueprint = $this->getBlueprint();
        $apiKey = env('AI_TRANSLATION_API_KEY', '');
        $translator = env('AI_TRANSLATOR_SERVICE', 'deepl');

       
        $fields = $blueprint->fields()->addValues([
            'translator' => $translator,  
            'api_key' => $apiKey,  
        ])->preProcess();  
    
     
    
    
        return view('ai-translator::settings', [
            'blueprint' => $blueprint->toPublishArray(),
            'values' => $fields->values(),
            'meta' => $fields->meta(),
        ]);
    }
    
    public function save(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->super) {
            return redirect('/');
        }
    
        $apiKey = $request->input('api_key');
        $translator = $request->input('translator');
    
        $this->setEnv('AI_TRANSLATOR_SERVICE', $translator);
        $this->setEnv('AI_TRANSLATION_API_KEY', $apiKey);
    
        return redirect('/cp/ai-translator/config')->with('success', 'Instellingen opgeslagen.');

    }
    

    protected function setEnv($key, $value)
    {
        $path = base_path('.env');
        $env = file_get_contents($path);

        if (strpos($env, $key) !== false) {
            $env = preg_replace("/^$key=.*/m", "$key=$value", $env);
        } else {
            $env .= "\n$key=$value";
        }

        file_put_contents($path, $env);
    }

    private function getBlueprint(): BlueprintContract
    {
        return Blueprint::make()->setContents(YAML::file(__DIR__.'/../resources/blueprints/config.yaml')->parse());
    }
}
