<?php

/**
 * TechDivision\Import\Subjects\AbstractSubject
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * PHP version 5
 *
 * @author    Tim Wagner <tw@appserver.io>
 * @copyright 2015 TechDivision GmbH <info@appserver.io>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/wagnert/csv-import
 * @link      http://www.appserver.io
 */

namespace TechDivision\Import\Subjects;

use Psr\Log\LoggerInterface;
use TechDivision\Import\Services\ProductProcessor;
use TechDivision\Import\Services\RegistryProcessor;
use TechDivision\Import\Services\RegistryAwareInterface;
use TechDivision\Import\Configuration\SubjectInterface As SubjectConfigurationInterface;
use TechDivision\Import\Observers\Product\ProductImportObserverInterface;

/**
 * An abstract action implementation.
 *
 * @author    Tim Wagner <tw@appserver.io>
 * @copyright 2015 TechDivision GmbH <info@appserver.io>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/wagnert/csv-import
 * @link      http://www.appserver.io
 */
abstract class AbstractSubject implements RegistryAwareInterface, SubjectInterface
{

    /**
     * The system configuration.
     *
     * @var \TechDivision\Import\Configuration\SubjectInterface
     */
    protected $configuration;

    /**
     * The system logger implementation.
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $systemLogger;

    /**
     * The RegistryProcessor instance to handle running threads.
     *
     * @var \TechDivision\Importer\Services\RegistryProcessor
     */
    protected $registryProcessor;

    /**
     * The processor to read/write the necessary product data.
     *
     * @var \TechDivision\Importer\Services\ProductProcessor
     */
    protected $productProcessor;

    /**
     * The actions unique serial.
     *
     * @var string
     */
    protected $serial;

    /**
     * The UUID of the file to process.
     *
     * @var string
     */
    protected $uid;

    /**
     * Array with the subject's callbacks.
     *
     * @var array
     */
    protected $callbacks = array();

    /**
     * Set's the system configuration.
     *
     * @param \TechDivision\Import\Configuration\Subject $configuration The system configuration
     */
    public function setConfiguration(SubjectConfigurationInterface $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * Return's the system configuration.
     *
     * @return \TechDivision\Import\Configuration\SubjectInterface The system configuration
     */
    public function getConfiguration()
    {
        return $this->configuration;
    }

    /**
     * Set's the system logger.
     *
     * @param \Psr\Log\LoggerInterface $systemLogger The system logger
     *
     * @return void
     */
    public function setSystemLogger(LoggerInterface $systemLogger)
    {
        $this->systemLogger = $systemLogger;
    }

    /**
     * Return's the system logger.
     *
     * @return \Psr\Log\LoggerInterface The system logger instance
     */
    public function getSystemLogger()
    {
        return $this->systemLogger;
    }

    /**
     * Sets's the RegistryProcessor instance to handle the running threads.
     *
     * @param \AppserverIo\RemoteMethodInvocation\RemoteObjectInterface $registryProcessor
     *
     * @return void
     */
    public function setRegistryProcessor($registryProcessor)
    {
        $this->registryProcessor = $registryProcessor;
    }

    /**
     * Return's the RegistryProcessor instance to handle the running threads.
     *
     * @return \AppserverIo\RemoteMethodInvocation\RemoteObjectInterface The instance
     */
    public function getRegistryProcessor()
    {
        return $this->registryProcessor;
    }

    /**
     * Set's the product processor instance.
     *
     * @param Importer\Csv\Services\Pdo\ProductProcessor $productProcessor The product processor instance
     *
     * @return void
     */
    public function setProductProcessor($productProcessor)
    {
        $this->productProcessor = $productProcessor;
    }

    /**
     * Return's the product processor instance.
     *
     * @return \Importer\Csv\Services\Pdo\ProductProcessor The product processor instance
     */
    public function getProductProcessor()
    {
        return $this->productProcessor;
    }

    /**
     * Set's the unique serial for this import process.
     *
     * @param string $serial The unique serial
     *
     * @return void
     */
    public function setSerial($serial)
    {
        $this->serial = $serial;
    }

    /**
     * Return's the unique serial for this import process.
     *
     * @return string The unique serial
     */
    public function getSerial()
    {
        return $this->serial;
    }

    /**
     * Set's the UUID of the file to process.
     *
     * @param string $uid The UUID
     *
     * @return void
     */
    public function setUid($uid)
    {
        $this->uid = $uid;
    }

    /**
     * Return's the UUID of the file to process.
     *
     * @return $uid The UUID
     */
    public function getUid()
    {
        return $this->uid;
    }

    /**
     * Return's the source date format to use.
     *
     * @return string The source date format
     */
    public function getSourceDateFormat()
    {
        return $this->getConfiguration()->getSourceDateFormat();
    }

    /**
     * Return's the initialized PDO connection.
     *
     * @return \PDO The initialized PDO connection
     */
    public function getConnection()
    {
        return $this->getProductProcessor()->getConnection();
    }

    /**
     * Intializes the previously loaded global data for exactly one bunch.
     *
     * @return void
     * @see \Importer\Csv\Actions\ProductImportAction::prepare()
     */
    public function setUp()
    {

        // prepare the callbacks
        foreach ($this->getConfiguration()->getCallbacks() as $callbacks) {
            $this->prepareCallbacks($callbacks);
        }
    }

    /**
     * Prepare the callbacks defined in the system configuration.
     *
     * @param array  $callbacks The array with the callbacks
     * @param string $type      The actual callback type
     *
     * @return void
     */
    public function prepareCallbacks(array $callbacks, $type = null)
    {

        // iterate over the array with callbacks and prepare them
        foreach ($callbacks as $key => $callback) {
            // we have to initialize the type only on the first level
            if ($type == null) {
                $type = $key;
            }

            // query whether or not we've an subarry or not
            if (is_array($callback)) {
                $this->prepareCallbacks($callback, $type);
            } else {
                $this->registerCallback($type, $callback);
            }
        }
    }

    /**
     * Register the passed class name as callback with the specific type and key.
     *
     * @param string $type      The callback type to register the callback with
     * @param string $className The callback class name
     *
     * @return void
     */
    public function registerCallback($type, $className)
    {

        // query whether or not the array with the callbacks for the
        // passed type has already been initialized, or not
        if (!isset($this->callbacks[$type])) {
            $this->callbacks[$type] = array();
        }

        // append the callback with the instance of the passed type
        $this->callbacks[$type][] = $this->observerFactory($className);
    }

    /**
     * Initialize and return a new observer of the passed type.
     *
     * @param string $className The type of the observer to instanciate
     *
     * @return \TechDivision\Import\Observers\ObserverInterface The observer instance
     */
    public function observerFactory($className)
    {
        return new $className($this);
    }

    /**
     * Return's the array with callbacks for the passed type.
     *
     * @param string $type The type of the callbacks to return
     *
     * @return array The callbacks
     */
    public function getCallbacksByType($type)
    {

        // initialize the array for the callbacks
        $callbacks = array();

        // query whether or not callbacks for the type are available
        if (isset($this->callbacks[$type])) {
            $callbacks = $this->callbacks[$type];
        }

        // return the array with the type's callbacks
        return $callbacks;
    }

    /**
     * Return's the array with the available callbacks.
     *
     * @return array The callbacks
     */
    public function getCallbacks()
    {
        return $this->callbacks;
    }

    /**
     * Imports the passed row into the database.
     *
     * If the import failed, the exception will be catched and logged,
     * but the import process will be continued.
     *
     * @param array $row The row with the data to be imported
     *
     * @return void
     */
    public function importRow(array $row)
    {

        // process the callbacks
        foreach (array_keys($this->getCallbacks()) as $type) {
            // invoke the pre-create callbacks
            foreach ($this->getCallbacksByType($type) as $observer) {
                if ($observer instanceof ProductImportObserverInterface) {
                    $row = $observer->handle($row);
                }
            }
        }
    }
}