<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;

class ServicesGenerate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'services:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate service-templates.yaml based on /templates/compose directory';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        ray()->clearAll();
        $files = array_diff(scandir(base_path('templates/compose')), ['.', '..']);
        $files = array_filter($files, function ($file) {
            return strpos($file, '.yaml') !== false;
        });
        $serviceTemplatesJson = [];
        foreach ($files as $file) {
            $parsed = $this->process_file($file);
            if ($parsed) {
                $name = data_get($parsed, 'name');
                $parsed = data_forget($parsed, 'name');
                $serviceTemplatesJson[$name] = $parsed;
            }
        }
        $serviceTemplatesJson = json_encode($serviceTemplatesJson, JSON_PRETTY_PRINT);
        file_put_contents(base_path('templates/service-templates.json'), $serviceTemplatesJson);
    }

    private function process_file($file)
    {
        $serviceName = str($file)->before('.yaml')->value();
        $content = file_get_contents(base_path("templates/compose/$file"));
        // $this->info($content);
        $ignore = collect(preg_grep('/^# ignore:/', explode("\n", $content)))->values();
        if ($ignore->count() > 0) {
            $ignore = (bool)str($ignore[0])->after('# ignore:')->trim()->value();
        } else {
            $ignore = false;
        }
        if ($ignore) {
            $this->info("Ignoring $file");
            return;
        }
        $this->info("Processing $file");
        $documentation = collect(preg_grep('/^# documentation:/', explode("\n", $content)))->values();
        if ($documentation->count() > 0) {
            $documentation = str($documentation[0])->after('# documentation:')->trim()->value();
        } else {
            $documentation = 'https://coolify.io/docs';
        }

        $slogan = collect(preg_grep('/^# slogan:/', explode("\n", $content)))->values();
        if ($slogan->count() > 0) {
            $slogan = str($slogan[0])->after('# slogan:')->trim()->value();
        } else {
            $slogan = str($file)->headline()->value();
        }
        $env_file = collect(preg_grep('/^# env_file:/', explode("\n", $content)))->values();
        if ($env_file->count() > 0) {
            $env_file = str($env_file[0])->after('# env_file:')->trim()->value();
        } else {
            $env_file = null;
        }

        $tags = collect(preg_grep('/^# tags:/', explode("\n", $content)))->values();
        if ($tags->count() > 0) {
            $tags = str($tags[0])->after('# tags:')->trim()->explode(',')->map(function ($tag) {
                return str($tag)->trim()->lower()->value();
            })->values();
        } else {
            $tags = null;
        }
        $json = Yaml::parse($content);
        $yaml = base64_encode(Yaml::dump($json, 10, 2));
        $payload = [
            'name' => $serviceName,
            'documentation' => $documentation,
            'slogan' => $slogan,
            'compose' => $yaml,
            'tags' => $tags,
        ];
        if ($env_file) {
            $env_file_content = file_get_contents(base_path("templates/compose/$env_file"));
            $env_file_base64 = base64_encode($env_file_content);
            $payload['envs'] = $env_file_base64;
        }
        return $payload;
    }
}
