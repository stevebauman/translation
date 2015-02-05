<?php

namespace Stevebauman\Translation\Commands;

use Stevebauman\Translation\Exceptions\Commands\DirectoryNotFoundException;
use Symfony\Component\Console\Input\InputArgument;
use Illuminate\Console\Command;

/**
 * Class ScanCommand
 * @package Stevebauman\Translation\Commands
 */
class ScanCommand extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'translation:scan';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scans the specified directory for translations';

    /**
     * The current
     *
     * @var string
     */
    private $scanDir = '';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function fire()
    {
        $directory = base_path($this->argument('directory'));

        $this->line('Checking directory...');

        $this->verifyDirectory($directory);

        $this->line('Scanning specified directory...');

        $this->scanDirectory($directory);

    }

    /**
     * Returns an array of accepted command arguments
     *
     * @return array
     */
    protected function getArguments()
    {
        return array(
            array(
                'directory', InputArgument::REQUIRED, 'Directory of files to scan'
            )
        );
    }

    /**
     * Verifies if the directory specified exists
     *
     * @param $directory
     * @return bool
     * @throws DirectoryNotFoundException
     */
    private function verifyDirectory($directory)
    {
        if(is_dir($directory)) {

            return true;

        } else {

            $message = sprintf('Directory: %s does not exist', $directory);

            throw new DirectoryNotFoundException($message);

        }
    }

    private function scanDirectory($directory)
    {
        $results = $this->dirToArray($directory);

        $this->processScan($results);
    }

    /**
     * Processes the scan command
     *
     * @param $array
     * @return mixed
     */
    private function processScan($array)
    {
        foreach($array as $file)
        {
            if(is_array($file)) {

                return $this->processScan($file);

            } else {

                $content = file_get_contents($file);

                $messages = $this->parseContent($content);

            }
        }
    }

    /**
     * Parses content from a file and returns an array of messages to be inserted
     * into the database
     *
     * @param $content
     * @return array
     */
    private function parseContent($content)
    {
        $messages = [];
        /*
         * Regex used:
         *
         * {{'AJAX framework'|_}}
         * {{\s*'([^'])+'\s*[|]\s*_\s*}}
         *
         * {{'AJAX framework'|_(variables)}}
         * {{\s*'([^'])+'\s*[|]\s*_\s*\([^\)]+\)\s*}}
         *
         * {{ _t('Translation Text') }}
         * {{\s*_t[^\(]*([^'])+'[^\(]*\s*}}
         */
        $quoteChar = preg_quote("'");


        preg_match_all('#{{\s*'.$quoteChar.'([^'.$quoteChar.']+)'.$quoteChar.'\s*[|]\s*_\s*}}#', $content, $match);
        if (isset($match[1])) $messages = array_merge($messages, $match[1]);

        preg_match_all('#{{\s*'.$quoteChar.'([^'.$quoteChar.']+)'.$quoteChar.'\s*[|]\s*_\s*\([^\)]+\)\s*}}#', $content, $match);
        if (isset($match[1])) $messages = array_merge($messages, $match[1]);

        $quoteChar = preg_quote('"');
        preg_match_all('#{{\s*'.$quoteChar.'([^'.$quoteChar.']+)'.$quoteChar.'\s*[|]\s*_\s*}}#', $content, $match);
        if (isset($match[1])) $messages = array_merge($messages, $match[1]);

        preg_match_all('#{{\s*'.$quoteChar.'([^'.$quoteChar.']+)'.$quoteChar.'\s*[|]\s*_\s*\([^\)]+\)\s*}}#', $content, $match);
        if (isset($match[1])) $messages = array_merge($messages, $match[1]);

        return $messages;
    }

    /**
     * Scans recursively and returns an array of files/folders in the specified directory
     *
     * @param $dir
     * @return array
     */
    private function dirToArray($dir) {

        $result = array();

        $cdir = scandir($dir);

        foreach ($cdir as $key => $value)
        {
            if (!in_array($value,array(".","..",".gitignore","composer.json")))
            {
                if (is_dir($dir . DIRECTORY_SEPARATOR . $value))
                {
                    $result[$value] = $this->dirToArray($dir . DIRECTORY_SEPARATOR . $value);
                }
                else
                {
                    $result[] = $dir .  DIRECTORY_SEPARATOR . $value;
                }
            }
        }

        return $result;
    }

}