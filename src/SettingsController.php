<?php
namespace AiTranslator;

use Statamic\Http\Controllers\CP\CpController;
// use Statamic\Facades\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class SettingsController extends CpController
{
    public function index()
    {

        return view('ai-translator::settings');
    
    }
    public function save(Request $request)
    {
        $apiKey = $request->input('api_key');

        

        $this->setEnv('AI_TRANSLATION_API_KEY', $apiKey);

        return view('ai-translator::settings');

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
}
