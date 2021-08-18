<?php

namespace Phpreel\StaticPHP\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Url\Url;
use File;
use Artisan;

class GenerateStatic extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate:static';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generates a static version of your Laravel application.';

    /**
     * The starting url.
     *
     * @var string
     */
    protected $currentUrl;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('In order to work, phpReel Static has to connect to a server running your application. You must provide a host and an available port for it.');
        $host = $this->ask('Host', '127.0.0.1');
        $port = $this->ask('Port', '8000');
        $this->currentUrl = "http://$host:$port/";


        //Test if the host is up
        $dom = new \DOMDocument();
        @$dom->loadHTMLFile($this->currentUrl);
        
        if($dom->saveHTML() == "\n") {
            $this->error("phpReel Static has failed to connect to the host. Please make sure the host you specified is running.");
            return;
        }
        else {
            $this->info("Your static website will be generated shortly...");
        }

        $this->crawlPage($this->currentUrl);

        $this->newLine();
        $this->info("Your static website has been fully generated. You can find it in the static folder situated in the root of your app.");
        $this->newLine();
        $this->line("To regenerate the static website please run again the command php artisan generate:static");
    }

    private function manageAssets($dom, $elementTagName, $attribute, $url)
    {
        $cssAssets = $dom->getElementsByTagName($elementTagName);

        foreach($cssAssets as $cssAsset) {
            $href = $cssAsset->getAttribute($attribute);
            $hrefUrl = Url::fromString($href);
            $currentUrl = $hrefUrl->getScheme() . "://" . $hrefUrl->getHost() . ":" . $hrefUrl->getPort();

            //If css is an external file
            if($href != '' && $this->currentUrl == $currentUrl)
            {
                $path = "";

                //Local file path where the css file will be stored locally
                if($hrefUrl->getPath() != '/')
                {
                    $path = $hrefUrl->getPath();
                    $path = explode('/', $path, -1);
                    $path = implode('/', $path);
                }     
    
                if(!File::isDirectory("static" . $path))
                {
                    File::makeDirectory("static" . $path, $mode = 0777, true);
                }
    
                //Name of the css file as a static local file
                $fileName = explode('/', $hrefUrl->getPath());
                $fileName = $fileName[count($fileName) - 1];
                
                //Store the css file as a static local file
                $style = file_get_contents($href);
                
                File::put("static" . $path . "/" . $fileName, $style);
    
                $path = self::generateHtmlFileLocation($url, substr($hrefUrl->getPath(), 1));
                //Update href attribute by linking it to the local css file
                $cssAsset->setAttribute($attribute, $path);
                $dom->saveHTML();

                $this->info("Generated file: $path");
            }
        }
    }

    /**
     * Generates path to a specific file starting from the "root" by using "../" operator
     * 
     * @var string $url of the main page (the page that contains the links)
     * @var string $path basic initial path to the file in the filesystem
     * 
     * @return string generated path based on the basic path and the amount "../" necessary 
     */
    private function generateHtmlFileLocation($url, $path)
    {
        //Update href value depending on the location of the static html files
        $countSlash = substr_count(Url::fromString($url)->getPath(), '/');
        $backDirectory = "";

        for($i=0;$i<$countSlash - 1;$i++)
        {
            $backDirectory .= "../";
        }

        $path = $backDirectory . $path;

        return $path;
    }

    public function crawlPage($url)
    {
        static $seen = array();
        $seen[$url] = true;

        $dom = new \DOMDocument();
        @$dom->loadHTMLFile($url);

        //Manage CSS assets
        self::manageAssets($dom, 'link', 'href', $url);

        //Manage JS assets
        self::manageAssets($dom, 'script', 'src', $url);

        //Manage img assets
        self::manageAssets($dom, 'img', 'src', $url);

        //Update all urls from a page
        $aTags = $dom->getElementsByTagName('a');
        foreach($aTags as $aTag) 
        {
            $href = $aTag->getAttribute('href');
            $hrefUrl = Url::fromString($href);
            $currentUrl = $hrefUrl->getScheme() . "://" . $hrefUrl->getHost() . ":" . $hrefUrl->getPort() . "/";
            
            if($this->currentUrl == $currentUrl)
            {
                //Create path to the local html files
                $path = self::generateHtmlFileLocation($url, substr($hrefUrl->getPath(), 1));

                $aTag->setAttribute('href', $path . '/index.html');
                $dom->saveHTML();

                if(!isset($seen[$href . '/']))
                {
                    $this->crawlPage($href . "/");
                }
            }
        }

        $localPath = Url::fromString($url);

        if(!File::isDirectory("static" . $localPath->getPath()))
        {
            File::makeDirectory("static" . $localPath->getPath(), $mode = 0777, true);
        }

        File::put("static" . $localPath->getPath() . "index.html", $dom->saveHTML());

        $this->info("Generated file: " . "static" . $localPath->getPath() . "index.html");
    }
}
