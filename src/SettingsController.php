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

        // Haal de huidige API-sleutel op uit Statamic instellingen
        // $apiKey = Settings::get('ai_translator.api_key');
        return view('ai-translator::settings');
    
    }
    public function save(Request $request)
    {
        $apiKey = $request->input('api_key');

        // Settings::set('ai_translator.api_key', $apiKey);
        

        $this->setEnv('AI_TRANSLATION_API_KEY', $apiKey);

        return redirect()->route('ai-translator::settings')
                         ->with('success', 'Instellingen succesvol opgeslagen!');
    }

    // Hulpmethode om de .env te bewerken
    protected function setEnv($key, $value)
    {
        $path = base_path('.env');
        $env = file_get_contents($path);

        if (strpos($env, $key) !== false) {
            // Vervang de bestaande waarde als de sleutel al bestaat
            $env = preg_replace("/^$key=.*/m", "$key=$value", $env);
        } else {
            // Voeg een nieuwe sleutel toe als deze nog niet bestaat
            $env .= "\n$key=$value";
        }

        // Sla het gewijzigde .env bestand weer op
        file_put_contents($path, $env);
    }
}
