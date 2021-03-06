<?php

/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Magento
 */
namespace jtl\Connector\Magento\Controller;

use jtl\Connector\Core\Logger\Logger;
use jtl\Connector\Core\Model\DataModel;
use jtl\Connector\Core\Model\QueryFilter;
use jtl\Connector\Core\Rpc\Error;
use jtl\Connector\Model\ConnectorServerInfo;
use jtl\Connector\Result\Action;
use jtl\Connector\Magento\Magento;
use jtl\Connector\Model\ConnectorIdentification;

/**
 * Description of Connector
 *
 * @access public
 * @author Christian Spoo <christian.spoo@jtl-software.com>
 */
class Connector extends AbstractController
{
    private $controllers = array(
        'Category',
        'Customer',
        'CustomerOrder',
        'GlobalData',
        'Image',
        'Product'
    );

    public function push(DataModel $model)
    {
        
    }

    public function pull(QueryFilter $filter)
    {

    }

    public function delete(DataModel $model)
    {
        
    }

    public function statistic(QueryFilter $filter)
    {
        $action = new Action();
        $action->setHandled(true);

        try {
            $result = array();

            foreach ($this->controllers as $controller) {
                $controller = __NAMESPACE__ . '\\' . $controller;
                $obj = new $controller();

                if (method_exists($obj, 'statistic')) {
                    $method_result = $obj->statistic($filter);

                    $result[] = $method_result->getResult();
                }
            }

            $action->setResult($result);
        }
        catch (\Exception $exc) {
            $err = new Error();
            $err->setCode($exc->getCode());
            $err->setMessage($exc->getMessage());
            $action->setError($err);
        }
        
        return $action;
    }

    /**
     * Identify
     *
     * @return \jtl\Connector\Result\Action
     */
    public function identify()
    {
        $action = new Action();
        $action->setHandled(true);

        $returnMegaBytes = function($value) {
            $value = trim($value);
            $unit = strtolower($value[strlen($value) - 1]);
            switch ($unit) {
                case 'g':
                    $value = substr($value, 0, strlen($value) - 1);
                    return ($value * 1024);
                case 'm':
                    $value = substr($value, 0, strlen($value) - 1);
                    return (int)$value;
                case 'k':
                    $value = substr($value, 0, strlen($value) - 1);
                    return (int)floor($value / 1024);
                default:
                    return (int)floor($value / 1048576);
            }

            return (int) $value;
        };

        $serverInfo = new ConnectorServerInfo();
        $serverInfo->setMemoryLimit($returnMegaBytes(ini_get('memory_limit')))
            ->setExecutionTime((int) ini_get('max_execution_time'))
            ->setPostMaxSize($returnMegaBytes(ini_get('post_max_size')))
            ->setUploadMaxFilesize($returnMegaBytes(ini_get('upload_max_filesize')));

        $identification = new ConnectorIdentification();
        $identification->setEndpointVersion('1.4.5.0')
            ->setPlatformName('Magento')
            ->setPlatformVersion(\Mage::getVersion())
            ->setProtocolVersion(Application()->getProtocolVersion())
            ->setServerInfo($serverInfo);

        $action->setResult($identification);

        return $action;
    }

    /**
     * Finish
     *
     * @return \jtl\Connector\Result\Action
     */
    public function finish()
    {
        $action = new Action();
        $action->setHandled(true);

        Logger::write('reindexing everything...');
        Magento::getInstance()->reindexEverything();
        Logger::write('indexing has finished');

        $action->setResult(true);

        return $action;
    }
}
