<?php

namespace Stevebauman\Translation\Commands;

use Stevebauman\Translation\Exceptions\Commands\DirectoryNotFoundException;
use Stevebauman\Translation\Translation;
use Symfony\Component\Console\Input\InputArgument;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;

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
     * @var Translation
     */
    private $translator;

    /**
     * Constructor.
     *
     * @param Translation $translation
     */
    public function __construct(Translation $translation)
    {
        $this->translator = $translation;

        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function fire()
    {
        $directory = base_path($this->argument('directory'));

        $locale = $this->option('locale');

        if($locale) $this->translator->setLocale($locale);

        $this->line('Checking directory...');

        $this->verifyDirectory($directory);

        $this->line('Scanning specified directory...');

        $added = $this->scanDirectory($directory);

        $message = sprintf('Successfully found: %s translations', $added);

        $this->info($message);
    }

    /**
     * Returns an array of accepted command arguments
     *
     * @return array
     */
    protected function getArguments()
    {
        return array(
            array('directory', InputArgument::REQUIRED, 'The directory to search for translations in')
        );
    }

    /**
     * Returns an array of accepted command options
     *
     * @return array
     */
    protected function getOptions()
    {
        return array(
            array('locale', null, InputOption::VALUE_REQUIRED, 'The locale to generate the translations for')
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
        if(is_dir($directory)) return true;

        $message = sprintf('Directory: %s does not exist', $directory);

        throw new DirectoryNotFoundException($message);
    }

    /**
     * Scans the inserted directory string and processes each file that's returned
     *
     * @param string $directory
     * @return int
     */
    private function scanDirectory($directory)
    {
        $files = $this->dirToArray($directory);

        $results = $this->processScan($files);

        foreach($results as $translation)
        {
            $this->translator->translate($translation);
        }

        return count($results);
    }

    /**
     * Processes the scan command
     *
     * @param array $files
     * @return mixed
     */
    private function processScan($files = array())
    {
        $messages = array();

        foreach($files as $file)
        {

            if(is_array($file))
            {
                $messages[] = $this->processScan($file);
            } else
            {
                $content = file_get_contents($file);

                $messages[] = $this->parseContent($content);
            }
        }

       return array_flatten(array_filter($messages));
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
         * Regex: _t(\'(.*?)\')
         * Matches: _t('Test')
         */
        preg_match_all("#_t\(\'(.*?)\'\)#", $content, $match);
        if (isset($match[1])) $messages = array_merge($messages, $match[1]);

        /*
         * Regex: _t(\"(.*?)\")
         * Matches: _t("Test")
         */
        preg_match_all('#_t\(\"(.*?)\"\)#', $content, $match);
        if (isset($match[1])) $messages = array_merge($messages, $match[1]);

        /*
         * Regex: Translation::translate(\'(.*?)\')
         * Matches: Translation::translate('Test')
         */
        preg_match_all('#Translation::translate\(\'(.*?)\'\)#', $content, $match);
        if (isset($match[1])) $messages = array_merge($messages, $match[1]);

        /*
         * Regex: Translation::translate(\'(.*?)\')
         * Matches: Translation::translate("Test")
         */
        preg_match_all('#Translation::translate\(\"(.*?)\"\)#', $content, $match);
        if (isset($match[1])) $messages = array_merge($messages, $match[1]);

        return $messages;
    }

    /**
     * Scans recursively and returns an array of files/folders in the specified directory
     *
     * @param $dir
     * @return array
     */
    private function dirToArray($dir)
    {
        $result = array();

        $cdir = scandir($dir);

        foreach ($cdir as $key => $value)
        {
            if ( ! in_array($value, array(".", "..")))
            {
                if (is_dir($dir . DIRECTORY_SEPARATOR . $value))
                {
                    $result[$value] = $this->dirToArray($dir . DIRECTORY_SEPARATOR . $value);
                } else
                {
                    $result[] = $dir . DIRECTORY_SEPARATOR . $value;
                }
            }
        }

        return $result;
    }
}