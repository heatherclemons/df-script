<?php

namespace DreamFactory\Core\Script\Models;

use DreamFactory\Core\Exceptions\ServiceUnavailableException;
use DreamFactory\Core\Models\BaseServiceConfigModel;
use DreamFactory\Core\Models\ServiceCacheConfig;
use Illuminate\Database\Query\Builder;

/**
 * ScriptConfig
 *
 * @property integer $service_id
 * @property string  $content
 * @property string  $config
 * @method static Builder|ScriptConfig whereServiceId($value)
 * @method static Builder|ScriptConfig whereType($value)
 */
class ScriptConfig extends BaseServiceConfigModel
{
    protected $table = 'script_config';

    protected $fillable = [
        'service_id',
        'content',
        'config',
        'queued',
        'storage_service_id',
        'scm_reference',
        'scm_repository',
        'storage_path',
        'implements_access_list',
    ];

    // deprecated, service has type designation now
    protected $hidden = ['type'];

    protected $casts = [
        'service_id'             => 'integer',
        'config'                 => 'array',
        'queued'                 => 'boolean',
        'storage_service_id'     => 'integer',
        'implements_access_list' => 'boolean'
    ];

    /**
     * @var array Extra config to pass to any config handler
     */
    protected $engine = [];

    public static function getType()
    {
        return '';
    }

    /**
     * {@inheritdoc}
     */
    public static function getConfig($id, $local_config = null, $protect = true)
    {
        $config = parent::getConfig($id, $local_config, $protect);

        $cacheConfig = ServiceCacheConfig::whereServiceId($id)->first();
        $config = array_merge($config, ($cacheConfig ? $cacheConfig->toArray() : []));

        return $config;
    }

    /**
     * {@inheritdoc}
     */
    public static function setConfig($id, $config, $local_config = null)
    {
        if (!empty($disable = config('df.scripting.disable'))) {
            switch (strtolower($disable)) {
                case 'all':
                    throw new ServiceUnavailableException("All scripting is disabled for this instance.");
                    break;
                default:
                    $type = static::getType();
                    if (!empty($type) && (false !== stripos($disable, $type))) {
                        throw new ServiceUnavailableException("Scripting with $type is disabled for this instance.");
                    }
                    break;
            }
        }

        ServiceCacheConfig::setConfig($id, $config, $local_config);

        return parent::setConfig($id, $config, $local_config);
    }

    /**
     * {@inheritdoc}
     */
    public static function storeConfig($id, $config)
    {
        ServiceCacheConfig::storeConfig($id, $config);

        parent::storeConfig($id, $config);
    }

    /**
     * {@inheritdoc}
     */
    public static function getConfigSchema()
    {
        $schema = parent::getConfigSchema();
        $schema = array_merge($schema, ServiceCacheConfig::getConfigSchema());

        return $schema;
    }

    /**
     * @param array $schema
     */
    protected static function prepareConfigSchemaField(array &$schema)
    {
        parent::prepareConfigSchemaField($schema);

        switch ($schema['name']) {
            case 'content':
                $schema['label'] = 'Content';
                $schema['type'] = 'text';
                $schema['description'] =
                    'The content of the script written in the appropriate language.';
                break;
            case 'queued':
                $schema['label'] = 'Queue For Later Execution';
                $schema['description'] =
                    'Select to queue the script for later execution ' .
                    '(queuing success or failure returned to client immediately), ' .
                    'un-select to process the script upon calling the API.';
                break;
            case 'config':
                $schema['label'] = 'Additional Configuration';
                $schema['type'] = 'object';
                $schema['object'] =
                    [
                        'key'   => ['label' => 'Name', 'type' => 'string'],
                        'value' => ['label' => 'Value', 'type' => 'string']
                    ];
                $schema['description'] =
                    'An array of additional configuration needed for the script to run including the following:<br>' .
                    'Queued configuration, for details see https://laravel.com/docs/5.2/queues.<br>' .
                    '- QUEUED_DELAY = #seconds to delay the execution of the script.<br>' .
                    '- QUEUED_QUEUE= alternative queue from the system configuration.<br>' .
                    '- QUEUED_CONNECTION = alternative queue connection from the system configuration.';
                break;
            case 'storage_service_id':
                $schema['type'] = 'integer';
                break;
            case 'implements_access_list':
                $schema['label'] = 'Script Implements Access List';
                $schema['description'] =
                    'By default, the access list is generated by the OpenAPI specification provided. ' .
                    'To override, check this and implement the access list in the script.';
                break;
        }
    }
}
