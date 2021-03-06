<?php

namespace Modelizer\Selenium\Console;

use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Modelizer\Selenium\Traits\HelperTrait;
use ZipArchive;

class GetWebDriver extends Command
{
    use HelperTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'selenium:download 
                            {driver : Specify web driver (chrome|firefox)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Download web driver for selenium.';

    /**
     * Web driver.
     *
     * @var string
     */
    protected $driver;

    /**
     * User current operating system.
     *
     * @var string
     */
    protected $userOS;

    /**
     * Resource file of web driver (raw zip file).
     *
     * @var string
     */
    protected $resource;

    /**
     * Configuration of web drivers.
     *
     * @var array
     */
    protected $config;

    /**
     * Get driver.
     *
     * @return string
     */
    protected function getDriver()
    {
        return $this->driver ?: $this->driver =
            $this->argument('driver');
    }

    /**
     * Get user operating system.
     *
     * @return mixed
     */
    public function getUserOS()
    {
        return $this->userOS ?: $this->userOS = @$this->os[mb_strtolower(PHP_OS)];
    }

    /**
     * Loading drivers specific download path and version.
     *
     * @return mixed
     */
    public function getConfig()
    {
        return $this->config ?: $this->setConfig()->getConfig();
    }

    /**
     * Set the configuration for web drivers.
     *
     * @throws \ErrorException
     *
     * @return $this
     */
    public function setConfig()
    {
        $this->config = require static::prependPackagePath('config/drivers.php');

        $this->config = @$this->config[$this->getDriver()][$this->getUserOS()];

        if (!$this->config) {
            throw new \ErrorException("Currently {$this->getDriver()} not supported.");
        }

        return $this;
    }

    /**
     * Get web driver file name.
     *
     * @return string
     */
    protected function getFileName()
    {
        return "{$this->getUserOS()}-{$this->getDriver()}".($this->getUserOS() == 'win' ? '.exe' : '');
    }

    /**
     * Execute the console command.
     *
     * @param Client $client
     *
     * @return bool
     */
    public function handle(Client $client)
    {
        if (!$resource = $this->checkRequirements()->setConfig()->download($client)) {
            $this->error('Aborting...');

            return false;
        }

        $this->unzip($resource);

        $this->info('Download complete.');
    }

    /**
     * Check the requirements before downloading.
     *
     * @return $this|bool
     */
    protected function checkRequirements()
    {
        if (!$this->getUserOS()) {
            $this->error("Configuration is not available for $this->userOS operation system.");

            exit;
        }

        return $this;
    }

    /**
     * Downloading driver.
     *
     * @param $client
     *
     * @return bool|string
     */
    protected function download($client)
    {
        $resource = static::prependPackagePath('driver-file', true);

        if (is_file(base_path("vendor/bin/{$this->getFileName()}"))) {
            if (!$this->confirm(ucfirst($this->argument('driver')).' web driver file already exists. Would you still like to download it?')) {
                return false;
            }
        }

        // Appending zip extension
        $resource .= '.zip';

        $this->info("Downloading {$this->driver} web driver for {$this->userOS}...");

        // Downloading Driver
        $client->request('get', $this->getConfig()['url'], [
            'save_to' => \GuzzleHttp\Psr7\stream_for(fopen($resource, 'w')),
        ]);

        return $resource;
    }

    /**
     * Unzip the web driver raw file.
     *
     * @param $resource
     *
     * @throws \ErrorException
     *
     * @return $this
     */
    public function unzip(&$resource)
    {
        $this->info("Unzipping $resource file...");

        // New file will be saved to vendor/bin directory
        $newFileName = base_path('vendor/bin/'.$this->getFileName());

        $zip = new ZipArchive();
        if (!$zip->open($resource)) {
            throw new \ErrorException("Unable to unzip downloaded file $resource.");
        }

        // Renaming chrome driver
        $zip->extractTo(dirname($resource));
        $zip->close();

        // Renaming file
        rename(
            static::prependPackagePath($this->getConfig()['filename']),
            $newFileName
        );

        // make it executable for unix machine
        $this->userOS == 'win' ?: chmod($newFileName, 0744);

        // Delete the zip file
        unlink($resource);

        return $this;
    }
}
