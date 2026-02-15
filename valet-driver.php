cat > ValetDriver.php << 'EOF'
<?php

class WordPressSubdirValetDriver extends BasicValetDriver
{
    /**
     * Determine if the driver serves the request.
     */
    public function serves($sitePath, $siteName, $uri)
    {
        return is_dir($sitePath.'/cms') && file_exists($sitePath.'/cms/wp-config.php');
    }

    /**
     * Determine if the incoming request is for a static file.
     */
    public function isStaticFile($sitePath, $siteName, $uri)
    {
        $staticFilePath = $sitePath.'/cms'.$uri;

        if ($this->isActualFile($staticFilePath)) {
            return $staticFilePath;
        }

        return false;
    }

    /**
     * Get the fully resolved path to the application's front controller.
     */
    public function frontControllerPath($sitePath, $siteName, $uri)
    {
        $_SERVER['PHP_SELF'] = $uri;
        $_SERVER['SERVER_NAME'] = $_SERVER['HTTP_HOST'];

        return $sitePath.'/cms/index.php';
    }
}
EOF