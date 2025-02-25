<?php
namespace MispConnector;
require_once(ROOT . '/src/Lib/default/local_tool_connectors/CommonConnectorTools.php');
use CommonConnectorTools\CommonConnectorTools;
use Cake\Http\Client;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Exception\MethodNotAllowedException;
use Cake\Http\Client\Response;

class MispConnector extends CommonConnectorTools
{
    public $description = 'MISP connector, handling diagnostics, organisation and sharing group management of your instance. Synchronisation requests can also be managed through the connector.';

    public $connectorName = 'MispConnector';
    public $name = 'MISP';

    public $exposedFunctions = [
        'diagnosticsAction' => [
            'type' => 'index',
            'scope' => 'child',
            'params' => [
            ]
        ],
        'serverSettingsAction' => [
            'type' => 'index',
            'scope' => 'child',
            'params' => [
                'quickFilter',
                'sort',
                'direction',
                'page',
                'limit'
            ]
        ],
        'organisationsAction' => [
            'type' => 'index',
            'scope' => 'child',
            'params' => [
                'quickFilter',
                'limit',
                'page',
                'sort',
                'direction'
            ]
        ],
        'sharingGroupsAction' => [
            'type' => 'index',
            'scope' => 'child',
            'params' => [
                'quickFilter',
                'limit',
                'page',
                'sort',
                'direction'
            ]
        ],
        'restartWorkersAction' => [
            'type' => 'formAction',
            'scope' => 'childAction',
            'redirect' => 'diagnosticsAction'
        ],
        'fetchOrganisationAction' => [
            'type' => 'formAction',
            'scope' => 'childAction',
            'params' => [
                'uuid'
            ],
            'redirect' => 'organisationsAction'
        ],
        'fetchSelectedOrganisationsAction' => [
            'type' => 'formAction',
            'scope' => 'childAction',
            'params' => [
                'uuid'
            ],
            'redirect' => 'organisationsAction'
        ],
        'fetchSelectedSharingGroupsAction' => [
            'type' => 'formAction',
            'scope' => 'childAction',
            'params' => [
                'uuid'
            ],
            'redirect' => 'sharingGroupsAction'
        ],
        'pushOrganisationAction' => [
            'type' => 'formAction',
            'scope' => 'childAction',
            'params' => [
                'uuid'
            ],
            'redirect' => 'organisationsAction'
        ],
        'pushOrganisationsAction' => [
            'type' => 'formAction',
            'scope' => 'childAction',
            'params' => [
                'uuid'
            ],
            'redirect' => 'organisationsAction'
        ],
        'pushSharingGroupsAction' => [
            'type' => 'formAction',
            'scope' => 'childAction',
            'params' => [
                'uuid'
            ],
            'redirect' => 'sharingGroupsAction'
        ],
        'fetchSharingGroupAction' => [
            'type' => 'formAction',
            'scope' => 'childAction',
            'params' => [
                'uuid'
            ],
            'redirect' => 'sharingGroupsAction'
        ],
        'modifySettingAction' => [
            'type' => 'formAction',
            'scope' => 'childAction',
            'params' => [
                'setting',
                'value'
            ],
            'redirect' => 'serverSettingsAction'
        ],
        'serversAction' => [
            'type' => 'index',
            'scope' => 'child',
            'params' => [
                'quickFilter',
                'limit',
                'page',
                'sort',
                'direction'
            ]
        ],
        'usersAction' => [
            'type' => 'index',
            'scope' => 'child',
            'params' => [
                'quickFilter',
                'limit',
                'page',
                'sort',
                'direction'
            ]
        ],
        'batchAPIAction' => [
            'type' => 'batchAction',
            'scope' => 'childAction',
            'params' => [
                'method',
                'url',
                'body',
            ],
            'ui' => [
                'text' => 'Batch API',
                'icon' => 'terminal',
                'variant' => 'primary',
            ]
        ],
    ];
    public $version = '0.1';
    public $settings = [
        'url' => [
            'type' => 'text'
        ],
        'authkey' => [
            'type' => 'text'
        ],
        'skip_ssl' => [
            'type' => 'boolean'
        ],
    ];
    public $settingsPlaceholder = [
        'url' => 'https://your.misp.intance',
        'authkey' => '',
        'skip_ssl' => '0',
    ];

    public function addSettingValidatorRules($validator)
    {
        return $validator
            ->requirePresence('url')
            ->notEmptyString('url', __('An URL must be provided'))
            ->requirePresence('authkey')
            ->notEmptyString('authkey', __('An Authkey must be provided'))
            ->lengthBetween('authkey', [40, 40], __('The authkey must be 40 character long'))
            ->boolean('skip_ssl');
    }

    public function addExposedFunction(string $functionName): void
    {
        $this->exposedFunctions[] = $functionName;
    }

    public function getExposedFunction(string $functionName): array
    {
        if (!empty($this->exposedFunctions[$functionName])) {
            return $exposedFunctions[$functionName];
        } else {
            throw new NotFoundException(__('Invalid action requested.'));
        }
    }

    private function genHTTPClient(Object $connection, array $options=[]): Object
    {
        $settings = json_decode($connection->settings, true);
        $defaultOptions = [
            'headers' => [
                'Authorization' => $settings['authkey'],
            ],
        ];
        if (empty($options['type'])) {
            $options['type'] = 'json';
        }
        if (!empty($settings['skip_ssl'])) {
            $options['ssl_verify_peer'] = false;
            $options['ssl_verify_host'] = false;
            $options['ssl_verify_peer_name'] = false;
            $options['ssl_allow_self_signed'] = true;
        }
        $options = array_merge($defaultOptions, $options);
        $http = new Client($options);
        return $http;
    }

    public function HTTPClientGET(String $relativeURL, Object $connection, array $data=[], array $options=[]): Object
    {
        $settings = json_decode($connection->settings, true);
        $http = $this->genHTTPClient($connection, $options);
        $url = sprintf('%s%s', $settings['url'], $relativeURL);
        return $http->get($url, $data, $options);
    }

    public function HTTPClientPOST(String $relativeURL, Object $connection, $data, array $options=[]): Object
    {
        $settings = json_decode($connection->settings, true);
        $http = $this->genHTTPClient($connection, $options);
        $url = sprintf('%s%s', $settings['url'], $relativeURL);
        $this->logDebug(sprintf('%s %s %s', __('Posting data') . PHP_EOL, "POST {$url}" . PHP_EOL, json_encode($data)));
        return $http->post($url, $data, $options);
    }

    public function health(Object $connection): array
    {
        try {
            $response = $this->HTTPClientPOST('/users/view/me.json', $connection, '{}');
        } catch (\Exception $e) {
            return [
                'status' => 0,
                'message' => __('Connection issue.')
            ];
        }
        $responseCode = $response->getStatusCode();
        if ($response->isOk()) {
            $status = 1;
            $message = __('OK');
        } else if ($responseCode == 403){
            $status = 3;
            $message = __('Unauthorized');
        } else {
            $status = 0;
            $message = __('Something went wrong.');
        }

        return [
            'status' => $status,
            'message' => $message
        ];
    }

    private function getData(string $url, array $params): Response
    {
        if (empty($params['connection'])) {
            throw new NotFoundException(__('No connection object received.'));
        }
        if (!empty($params['sort'])) {
            $list = explode('.', $params['sort']);
            $params['sort'] = end($list);
        }
        $url = $this->urlAppendParams($url, $params);
        $response = $this->HTTPClientGET($url, $params['connection']);
        if ($response->isOk()) {
            return $response;
        } else {
            if (!empty($params['softError'])) {
                return $response;
            }
            $errorMsg = __('Could not GET from the requested resource for `{0}`. Remote returned:', $url) . PHP_EOL . $response->getStringBody();
            $this->logError($errorMsg);
            throw new NotFoundException($errorMsg);
        }
    }

    private function postData(string $url, array $params): Response
    {
        if (empty($params['connection'])) {
            $errorMsg = __('No connection object received.');
            $this->logError($errorMsg);
            throw new NotFoundException($errorMsg);
        }
        $url = $this->urlAppendParams($url, $params);
        if (!is_string($params['body'])) {
            $params['body'] = json_encode($params['body']);
        }
        $response = $this->HTTPClientPOST($url, $params['connection'], $params['body']);
        if ($response->isOk()) {
            return $response;
        } else {
            if (!empty($params['softError'])) {
                return $response;
            }
            $errorMsg = __('Could not post to the requested resource for `{0}`. Remote returned:', $url) . PHP_EOL . $response->getStringBody();
            $this->logError($errorMsg);
            throw new NotFoundException($errorMsg);
        }
    }

    public function urlAppendParams(string $url, array $params): string
    {
        if (!isset($params['validParams'])) {
            $validParams = [
                'quickFilter' => 'searchall',
                'sort' => 'sort',
                'page' => 'page',
                'direction' => 'direction',
                'limit' => 'limit'
            ];
        } else {
            $validParams = $params['validParams'];
        }
        foreach ($validParams as $param => $remoteParam) {
            if (!empty($params[$param])) {
                $url .= sprintf('/%s:%s', $remoteParam, $params[$param]);
            }
        }
        return $url;
    }

    public function diagnostics(array $params): array
    {
        $urlParams = h($params['connection']['id']) . '/serverSettingsAction';
        $response = $this->getData('/servers/serverSettings', $params);
        if (!$response->isOk()) {
            return [];
        }
        $data = $response->getJson();
        $issues = [];
        if ($data['version']['upToDate'] !== 'same') {
            $issues['version'] = [
                'type' => 'danger',
                'message' => __('Outdated ({0}).', $data['version']['current']),
                'remediation' => [
                    'icon' => 'fa-sync',
                    'title' => __('Update MISP'),
                    'url' => '/localTools/action/' . h($params['connection']['id']) . '/updateMISP'
                ]
            ];
        }
        if ($data['phpSettings']['memory_limit']['value'] < $data['phpSettings']['memory_limit']['recommended']) {
            $issues['php_memory'] = [
                'type' => 'warning',
                'message' => __('Low PHP memory ({0}M).', $data['phpSettings']['memory_limit']['value'])
            ];
        }
        $worker_issues = [];
        foreach ($data['workers'] as $queue => $worker_data) {
            if (in_array($queue, ['proc_accessible', 'scheduler', 'controls'])) {
                continue;
            }
            if (empty($worker_data['ok'])) {
                $worker_issues['down'][] = $queue;
            }
            if ($worker_data['jobCount'] > 100) {
                $worker_issues['stalled'][] = $queue;
            }
        }
        if (!empty($worker_issues['down'])) {
            $issues['workers_down'] = [
                'type' => 'danger',
                'message' => __('Worker(s) down: {0}', implode(', ', $worker_issues['down'])),
                'remediation' => [
                    'icon' => 'fa-sync',
                    'title' => __('Restart workers'),
                    'url' => '/localTools/action/' . h($params['connection']['id']) . '/restartWorkersAction'
                ]
            ];
        }

        if (!empty($worker_issues['stalled'])) {
            $issues['workers_stalled'] = [
                'type' => 'warning',
                'message' => __('Worker(s) stalled: {0}', implode(', ', $worker_issues['stalled'])),
                'remediation' => [
                    'icon' => 'fa-sync',
                    'title' => __('Restart workers'),
                    'url' => '/localTools/action/' . h($params['connection']['id']) . '/restartWorkersAction'
                ]
            ];
        }
        if (!empty($data['dbConfiguration'])) {
            foreach ($data['dbConfiguration'] as $dbConfig) {
                if ($dbConfig['name'] === 'innodb_buffer_pool_size' && $dbConfig['value'] < $dbConfig['recommended']) {
                    $issues['innodb_buffer_pool_size'] = [
                        'type' => 'warning',
                        'message' => __('InnoDB buffer pool size is low ({0}M).', (round($dbConfig['value']/1024/1024)))
                    ];
                }
            }
        }
        if (!empty($data['dbSchemaDiagnostics'])) {
            if ($data['dbSchemaDiagnostics']['expected_db_version'] > $data['dbSchemaDiagnostics']['actual_db_version'])
            $issues['schema_version'] = [
                'type' => 'danger',
                'message' => __('DB schame outdated.'),
                'remediation' => [
                    'icon' => 'fa-sync',
                    'title' => __('Update DB schema'),
                    'url' => '/localTools/action/' . h($params['connection']['id']) . '/updateSchemaAction'
                ]
            ];
        }
        return $issues;
    }

    public function restartWorkersAction(array $params): array
    {
        if ($params['request']->is(['get'])) {
            return [
                'data' => [
                    'title' => __('Restart workers'),
                    'description' => __('Would you like to trigger a restart of all attached workers?'),
                    'submit' => [
                        'action' => $params['request']->getParam('action')
                    ],
                    'url' => ['controller' => 'localTools', 'action' => 'action', $params['connection']['id'], 'restartWorkersAction']
                ]
            ];
        } elseif ($params['request']->is(['post'])) {
            $response = $this->postData('/servers/restartWorkers', $params);
            if ($response->isOk()) {
                return ['success' => 1, 'message' => __('Workers restarted.')];
            } else {
                return ['success' => 0, 'message' => __('Could not restart workers.')];
            }
        }



        $response = $this->postData('/servers/restartWorkers', $params);
        if ($response->isOk()) {
            return [
                'type' => 'success',
                'message' => __('Workers restarted.')
            ];
        } else {
            return [
                'type' => 'danger',
                'message' => __('Something went wrong.')
            ];
        }
    }


    public function diagnosticsAction(array $params): array
    {
        $diagnostics = $this->diagnostics($params);
        $data = [];
        foreach ($diagnostics as $error => $error_data) {
            $data[] = [
                'error' => $error,
                'type' => $error_data['type'],
                'message' => $error_data['message'],
                'remediation' => $error_data['remediation'] ?? false
            ];
        }
        return [
            'type' => 'index',
            'data' => [
                'data' => $data,
                'skip_pagination' => 1,
                'top_bar' => [
                    'children' => []
                ],
                'fields' => [
                    [
                        'name' => 'error',
                        'data_path' => 'error',
                        'name' => __('Error'),
                    ],
                    [
                        'name' => 'message',
                        'data_path' => 'message',
                        'name' => __('Message'),
                    ],
                    [
                        'name' => __('Remediation'),
                        'element' => 'function',
                        'function' => function($row, $context) {
                            $remediation = $context->Hash->extract($row, 'remediation');
                            if (!empty($remediation['title'])) {
                                echo sprintf(
                                    '<a href="%s" class="btn btn-primary btn-sm" title="%s"><i class="fa %s"></i></a>',
                                    h($remediation['url']),
                                    h($remediation['title']),
                                    h($remediation['icon'])
                                );
                            }
                        }
                    ]
                ],
                'title' => false,
                'description' => false,
                'pull' => 'right'
            ]
        ];
    }

    public function serverSettingsAction(array $params): array
    {
        $params['validParams'] = [
            'limit' => 'limit',
            'page' => 'page',
            'quickFilter' => 'searchall'
        ];
        $urlParams = h($params['connection']['id']) . '/serverSettingsAction';
        $response = $this->getData('/servers/serverSettings', $params);
        $data = $response->getJson();
        if (!empty($data['finalSettings'])) {
            $finalSettings = [
                'type' => 'index',
                'data' => [
                    'data' => $data['finalSettings'],
                    'skip_pagination' => 1,
                    'top_bar' => [
                        'children' => [
                            [
                                'type' => 'search',
                                'button' => __('Search'),
                                'placeholder' => __('Enter value to search'),
                                'data' => '',
                                'searchKey' => 'value',
                                'additionalUrlParams' => $urlParams
                            ]
                        ]
                    ],
                    'fields' => [
                        [
                            'name' => 'Setting',
                            'sort' => 'setting',
                            'data_path' => 'setting',
                        ],
                        [
                            'name' => 'Criticality',
                            'sort' => 'level',
                            'data_path' => 'level',
                            'arrayData' => [
                                0 => 'Critical',
                                1 => 'Recommended',
                                2 => 'Optional'
                            ],
                            'element' => 'array_lookup_field'
                        ],
                        [
                            'name' => __('Value'),
                            'sort' => 'value',
                            'data_path' => 'value',
                            'options' => 'options'
                        ],
                        [
                            'name' => __('Type'),
                            'sort' => 'type',
                            'data_path' => 'type',
                        ],
                        [
                            'name' => __('Error message'),
                            'sort' => 'errorMessage',
                            'data_path' => 'errorMessage',
                        ]
                    ],
                    'title' => false,
                    'description' => false,
                    'pull' => 'right',
                    'actions' => [
                        [
                            'open_modal' => '/localTools/action/' . h($params['connection']['id']) . '/modifySettingAction?setting={{0}}',
                            'modal_params_data_path' => ['setting'],
                            'icon' => 'download',
                            'reload_url' => '/localTools/action/' . h($params['connection']['id']) . '/ServerSettingsAction'
                        ]
                    ]
                ],
            ];

            if (!empty($params['quickFilter'])) {
                $needle = strtolower($params['quickFilter']);
                foreach ($finalSettings['data']['data'] as $k => $v) {
                    if (strpos(strtolower($v['setting']), $needle) === false) {
                        unset($finalSettings['data']['data'][$k]);
                    }
                }
                $finalSettings['data']['data'] = array_values($finalSettings['data']['data']);
            }
            return $finalSettings;
        } else {
            return [];
        }
    }

    public function serversAction(array $params): array
    {
        $params['validParams'] = [
            'limit' => 'limit',
            'page' => 'page',
            'quickFilter' => 'searchall'
        ];
        $urlParams = h($params['connection']['id']) . '/serversAction';
        $response = $this->getData('/servers/index', $params);
        $data = $response->getJson();
        if (!empty($data)) {
            return [
                'type' => 'index',
                'data' => [
                    'data' => $data,
                    'skip_pagination' => 1,
                    'top_bar' => [
                        'children' => [
                            [
                                'type' => 'search',
                                'button' => __('Search'),
                                'placeholder' => __('Enter value to search'),
                                'data' => '',
                                'searchKey' => 'value',
                                'additionalUrlParams' => $urlParams,
                                'quickFilter' => 'value'
                            ]
                        ]
                    ],
                    'fields' => [
                        [
                            'name' => 'Id',
                            'sort' => 'Server.id',
                            'data_path' => 'Server.id',
                        ],
                        [
                            'name' => 'Name',
                            'sort' => 'Server.name',
                            'data_path' => 'Server.name',
                        ],
                        [
                            'name' => 'Url',
                            'sort' => 'Server.url',
                            'data_path' => 'Server.url'
                        ],
                        [
                            'name' => 'Pull',
                            'sort' => 'Server.pull',
                            'element' => 'function',
                            'function' => function($row, $context) {
                                $pull = $context->Hash->extract($row, 'Server.pull')[0];
                                $pull_rules = $context->Hash->extract($row, 'Server.pull_rules')[0];
                                $pull_rules = json_encode(json_decode($pull_rules, true), JSON_PRETTY_PRINT);
                                echo sprintf(
                                    '<span title="%s" class="fa fa-%s"></span>',
                                    h($pull_rules),
                                    $pull ? 'check' : 'times'
                                );
                            }
                        ],
                        [
                            'name' => 'Push',
                            'element' => 'function',
                            'function' => function($row, $context) {
                                $push = $context->Hash->extract($row, 'Server.push')[0];
                                $push_rules = $context->Hash->extract($row, 'Server.push_rules')[0];
                                $push_rules = json_encode(json_decode($push_rules, true), JSON_PRETTY_PRINT);
                                echo sprintf(
                                    '<span title="%s" class="fa fa-%s"></span>',
                                    h($push_rules),
                                    $push ? 'check' : 'times'
                                );
                            }
                        ],
                        [
                            'name' => 'Caching',
                            'element' => 'boolean',
                            'data_path' => 'Server.caching_enabled'
                        ]
                    ],
                    'title' => false,
                    'description' => false,
                    'pull' => 'right'
                ]
            ];
        } else {
            return [];
        }
    }

    public function usersAction(array $params): array
    {
        $params['validParams'] = [
            'limit' => 'limit',
            'page' => 'page',
            'quickFilter' => 'searchall'
        ];
        $urlParams = h($params['connection']['id']) . '/usersAction';
        $response = $this->getData('/admin/users/index', $params);
        $data = $response->getJson();
        if (!empty($data)) {
            return [
                'type' => 'index',
                'data' => [
                    'data' => $data,
                    'skip_pagination' => 1,
                    'top_bar' => [
                        'children' => [
                            [
                                'type' => 'search',
                                'button' => __('Search'),
                                'placeholder' => __('Enter value to search'),
                                'data' => '',
                                'searchKey' => 'value',
                                'additionalUrlParams' => $urlParams,
                                'quickFilter' => 'value'
                            ]
                        ]
                    ],
                    'fields' => [
                        [
                            'name' => 'Id',
                            'sort' => 'User.id',
                            'data_path' => 'User.id',
                        ],
                        [
                            'name' => 'Organisation',
                            'sort' => 'Organisation.name',
                            'data_path' => 'Organisation.name',
                        ],
                        [
                            'name' => 'Email',
                            'sort' => 'User.email',
                            'data_path' => 'User.email',
                        ],
                        [
                            'name' => 'Role',
                            'sort' => 'Role.name',
                            'data_path' => 'Role.name'
                        ]
                    ],
                    'actions' => [
                        /*
                        [
                            'open_modal' => '/localTools/action/' . h($params['connection']['id']) . '/editUser?id={{0}}',
                            'modal_params_data_path' => ['User.id'],
                            'icon' => 'edit',
                            'reload_url' => '/localTools/action/' . h($params['connection']['id']) . '/editAction'
                        ],
                        [
                            'open_modal' => '/localTools/action/' . h($params['connection']['id']) . '/deleteUser?id={{0}}',
                            'modal_params_data_path' => ['User.id'],
                            'icon' => 'trash',
                            'reload_url' => '/localTools/action/' . h($params['connection']['id']) . '/serversAction'
                        ]
                        */
                    ],
                    'title' => false,
                    'description' => false,
                    'pull' => 'right'
                ]
            ];
        } else {
            return [];
        }
    }

    private function __compareOrgs(array $data, array $existingOrgs): array
    {
        foreach ($data as $k => $v) {
            $data[$k]['Organisation']['local_copy'] = false;
            if (!empty($existingOrgs[$v['Organisation']['uuid']])) {
                $remoteOrg = $existingOrgs[$v['Organisation']['uuid']];
                $localOrg = $v['Organisation'];
                $same = true;
                $fieldsToCheck = [
                    'nationality', 'sector', 'type', 'name'
                ];
                foreach (['nationality', 'sector', 'type', 'name'] as $fieldToCheck) {
                    if ($remoteOrg[$fieldToCheck] != $localOrg[$fieldToCheck]) {
                        $same = false;
                    }
                }
                $data[$k]['Organisation']['local_copy'] = $same ? 'same' : 'different';
            } else {
                $data[$k]['Organisation']['local_copy'] = 'not_found';
            }
        }
        return $data;
    }

    private function __compareSgs(array $data, array $existingSgs): array
    {
        foreach ($existingSgs as $k => $existingSg) {
            $existingSgs[$k]['org_uuids'] = [];
            foreach ($existingSg['sharing_group_orgs'] as $sgo) {
                $existingSgs[$k]['org_uuids'][$sgo['uuid']] = $sgo['_joinData']['extend'];
            }
        }
        foreach ($data as $k => $v) {
            $data[$k]['SharingGroup']['local_copy'] = false;
            $data[$k]['SharingGroup']['differences'] = [];
            if (!empty($existingSgs[$v['SharingGroup']['uuid']])) {
                $data[$k]['SharingGroup']['roaming'] = !empty($v['SharingGroup']['roaming']);
                $localSg = $existingSgs[$v['SharingGroup']['uuid']];
                $remoteSg = $v['SharingGroup'];
                $same = true;
                foreach (['description', 'name', 'releasability', 'active'] as $fieldToCheck) {
                    if ($remoteSg[$fieldToCheck] != $localSg[$fieldToCheck]) {
                        $same = false;
                        $data[$k]['differences']['metadata'] = true;
                    }
                }
                if ($v['Organisation']['uuid'] != $localSg['organisation']['uuid']) {
                    $same = false;
                    $data[$k]['SharingGroup']['differences']['uuid'] = true;
                }
                $v['org_uuids'] = [];
                foreach ($v['SharingGroupOrg'] as $sgo) {
                    $v['org_uuids'][$sgo['Organisation']['uuid']] = $sgo['extend'];
                }
                if (count($localSg['org_uuids']) !== count($v['org_uuids'])) {
                    $same = false;
                    $data[$k]['SharingGroup']['differences']['orgs_count'] = true;
                }
                foreach ($localSg['org_uuids'] as $org_uuid => $extend) {
                    if (!isset($v['org_uuids'][$org_uuid])) {
                        $same = false;
                        $data[$k]['SharingGroup']['differences']['remote_orgs_missing'] = true;
                    } else {
                        if ($extend != $v['org_uuids'][$org_uuid]) {
                            $same = false;
                            $data[$k]['SharingGroup']['differences']['remote_orgs_extend'] = true;
                        }
                    }
                }
                $data[$k]['SharingGroup']['local_copy'] = $same ? 'same' : 'different';
            } else {
                $data[$k]['SharingGroup']['local_copy'] = 'not_found';
            }
            $data[$k]['SharingGroup']['differences'] = array_keys($data[$k]['SharingGroup']['differences']);
        }
        return $data;
    }


    public function organisationsAction(array $params): array
    {
        $params['validParams'] = [
            'limit' => 'limit',
            'page' => 'page',
            'quickFilter' => 'searchall'
        ];
        $urlParams = h($params['connection']['id']) . '/organisationsAction';
        $response = $this->getData('/organisations/index', $params);
        $data = $response->getJson();
        $temp = $this->getOrganisations();
        $existingOrgs = [];
        foreach ($temp as $k => $v) {
            $existingOrgs[$v['uuid']] = $v;
            unset($temp[$k]);
        }
        $statusLevels = [
            'same' => [
                'colour' => 'success',
                'message' => __('Remote organisation is the same as local copy'),
                'icon' => 'check-circle'
            ],
            'different' => [
                'colour' => 'warning',
                'message' => __('Local and remote versions of the organisations are different.'),
                'icon' => 'exclamation-circle'
            ],
            'not_found' => [
                'colour' => 'danger',
                'message' => __('Local organisation not found'),
                'icon' => 'exclamation-triangle'
            ]
        ];
        $data = $this->__compareOrgs($data, $existingOrgs);
        if (!empty($data)) {
            return [
                'type' => 'index',
                'data' => [
                    'data' => $data,
                    'skip_pagination' => 1,
                    'top_bar' => [
                        'children' => [
                            [
                                'type' => 'simple',
                                'children' => [
                                    [
                                        'class' => 'hidden mass-select',
                                        'text' => __('Fetch selected organisations'),
                                        'html' => '<i class="fas fa-download"></i> ',
                                        'reload_url' => '/localTools/action/' . h($params['connection']['id']) . '/organisationsAction',
                                        'popover_url' => '/localTools/action/' . h($params['connection']['id']) . '/fetchSelectedOrganisationsAction'
                                    ],
                                    [
                                        'text' => __('Push organisations'),
                                        'html' => '<i class="fas fa-upload"></i> ',
                                        'class' => 'btn btn-primary',
                                        'reload_url' => '/localTools/action/' . h($params['connection']['id']) . '/organisationsAction',
                                        'popover_url' => '/localTools/action/' . h($params['connection']['id']) . '/pushOrganisationsAction'
                                    ]
                                ]
                            ],
                            [
                                'type' => 'search',
                                'button' => __('Search'),
                                'placeholder' => __('Enter value to search'),
                                'data' => '',
                                'searchKey' => 'value',
                                'additionalUrlParams' => $urlParams
                            ]
                        ]
                    ],
                    'fields' => [
                        [
                            'element' => 'selector',
                            'class' => 'short',
                            'data' => [
                                'id' => [
                                    'value_path' => 'Organisation.uuid'
                                ]
                            ]
                        ],
                        [
                            'name' => 'Name',
                            'sort' => 'Organisation.name',
                            'data_path' => 'Organisation.name',
                        ],
                        [
                            'name' => 'Status',
                            'sort' => 'Organisation.local_copy',
                            'data_path' => 'Organisation.local_copy',
                            'element' => 'status',
                            'status_levels' => $statusLevels
                        ],
                        [
                            'name' => 'uuid',
                            'sort' => 'Organisation.uuid',
                            'data_path' => 'Organisation.uuid'
                        ],
                        [
                            'name' => 'uuid',
                            'sort' => 'Organisation.uuid',
                            'data_path' => 'Organisation.uuid'
                        ],
                        [
                            'name' => 'nationality',
                            'sort' => 'Organisation.nationality',
                            'data_path' => 'Organisation.nationality'
                        ],
                        [
                            'name' => 'local',
                            'sort' => 'Organisation.local',
                            'data_path' => 'Organisation.local'
                        ],
                        [
                            'name' => 'sector',
                            'sort' => 'Organisation.sector',
                            'data_path' => 'Organisation.sector'
                        ]
                    ],
                    'title' => false,
                    'description' => false,
                    'pull' => 'right',
                    'actions' => [
                        [
                            'open_modal' => '/localTools/action/' . h($params['connection']['id']) . '/pushOrganisationAction?uuid={{0}}',
                            'modal_params_data_path' => ['Organisation.uuid'],
                            'icon' => 'upload',
                            'reload_url' => '/localTools/action/' . h($params['connection']['id']) . '/organisationsAction',
                            'complex_requirement' => [
                                'function' => function ($row, $options) {
                                    if ($row['Organisation']['local_copy'] === 'different') {
                                        return true;
                                    }
                                    return false;
                                }
                            ]
                        ],
                        [
                            'open_modal' => '/localTools/action/' . h($params['connection']['id']) . '/fetchOrganisationAction?uuid={{0}}',
                            'modal_params_data_path' => ['Organisation.uuid'],
                            'icon' => 'download',
                            'reload_url' => '/localTools/action/' . h($params['connection']['id']) . '/organisationsAction',
                            'complex_requirement' => [
                                'function' => function ($row, $options) {
                                    if ($row['Organisation']['local_copy'] === 'different' || $row['Organisation']['local_copy'] === 'not_found') {
                                        return true;
                                    }
                                    return false;
                                }
                            ]
                        ]
                    ]
                ]
            ];
        } else {
            return [];
        }
    }

    public function sharingGroupsAction(array $params): array
    {
        $params['validParams'] = [
            'limit' => 'limit',
            'page' => 'page',
            'quickFilter' => 'searchall'
        ];
        $urlParams = h($params['connection']['id']) . '/sharingGroupsAction';
        $response = $this->getData('/sharing_groups/index', $params);
        $data = $response->getJson();
        $temp = $this->getSharingGroups();
        $existingOrgs = [];
        foreach ($temp as $k => $v) {
            $existingSGs[$v['uuid']] = $v;
            unset($temp[$k]);
        }
        $data['response'] = $this->__compareSgs($data['response'], $existingSGs);
        $existingSGs = [];
        foreach ($temp as $k => $v) {
            $existingSGs[$v['uuid']] = $v;
            unset($temp[$k]);
        }
                $statusLevels = [
            'same' => [
                'colour' => 'success',
                'message' => __('Remote sharing group is the same as local copy'),
                'icon' => 'check-circle'
            ],
            'different' => [
                'colour' => 'warning',
                'message' => __('Local and remote versions of the sharing groups are different.'),
                'icon' => 'exclamation-circle'
            ],
            'not_found' => [
                'colour' => 'danger',
                'message' => __('Local sharing group not found'),
                'icon' => 'exclamation-triangle'
            ]
        ];
        if (!empty($data)) {
            return [
                'type' => 'index',
                'data' => [
                    'data' => $data['response'],
                    'skip_pagination' => 1,
                    'top_bar' => [
                        'children' => [
                            [
                                'type' => 'simple',
                                'children' => [
                                    [
                                        'class' => 'hidden mass-select',
                                        'text' => __('Fetch selected sharing groups'),
                                        'html' => '<i class="fas fa-download"></i> ',
                                        'reload_url' => '/localTools/action/' . h($params['connection']['id']) . '/sharingGroupsAction',
                                        'popover_url' => '/localTools/action/' . h($params['connection']['id']) . '/fetchSelectedSharingGroupsAction'
                                    ],
                                    [
                                        'text' => __('Push sharing groups'),
                                        'html' => '<i class="fas fa-upload"></i> ',
                                        'class' => 'btn btn-primary',
                                        'reload_url' => '/localTools/action/' . h($params['connection']['id']) . '/SharingGroupsAction',
                                        'popover_url' => '/localTools/action/' . h($params['connection']['id']) . '/pushSharingGroupsAction'
                                    ]
                                ]
                            ],
                        ]
                    ],
                    'fields' => [
                        [
                            'element' => 'selector',
                            'class' => 'short',
                            'data' => [
                                'id' => [
                                    'value_path' => 'SharingGroup.uuid'
                                ]
                            ]
                        ],
                        [
                            'name' => 'Name',
                            'sort' => 'SharingGroup.name',
                            'data_path' => 'SharingGroup.name',
                        ],
                        [
                            'name' => 'Status',
                            'sort' => 'SharingGroup.local_copy',
                            'data_path' => 'SharingGroup.local_copy',
                            'element' => 'status',
                            'status_levels' => $statusLevels
                        ],
                        [
                            'name' => 'Differences',
                            'data_path' => 'SharingGroup.differences',
                            'element' => 'list'
                        ],
                        [
                            'name' => 'uuid',
                            'sort' => 'SharingGroup.uuid',
                            'data_path' => 'SharingGroup.uuid'
                        ],
                        [
                            'name' => 'Organisations',
                            'sort' => 'SharingGroupOrg',
                            'data_path' => 'SharingGroupOrg',
                            'element' => 'count_summary',
                            'title' => 'foo'
                        ],
                        [
                            'name' => 'Roaming',
                            'sort' => 'SharingGroup.roaming',
                            'data_path' => 'SharingGroup.roaming',
                            'element' => 'boolean'
                        ]
                    ],
                    'title' => false,
                    'description' => false,
                    'pull' => 'right',
                    'actions' => [
                        [
                            'open_modal' => '/localTools/action/' . h($params['connection']['id']) . '/fetchSharingGroupAction?uuid={{0}}',
                            'modal_params_data_path' => ['SharingGroup.uuid'],
                            'icon' => 'download',
                            'reload_url' => '/localTools/action/' . h($params['connection']['id']) . '/SharingGroupsAction',
                            'complex_requirement' => [
                                'function' => function ($row, $options) {
                                    if ($row['SharingGroup']['roaming']) {
                                        return true;
                                    }
                                    return false;
                                }
                            ]
                        ]
                    ]
                ]
            ];
        } else {
            return [];
        }
    }

    public function fetchOrganisationAction(array $params): array
    {
        if ($params['request']->is(['get'])) {
            return [
                'data' => [
                    'title' => __('Fetch organisation'),
                    'description' => __('Fetch and create/update organisation ({0}) from MISP?', $params['uuid']),
                    'submit' => [
                        'action' => $params['request']->getParam('action')
                    ],
                    'url' => ['controller' => 'localTools', 'action' => 'action', $params['connection']['id'], 'fetchOrganisationAction', $params['uuid']]
                ]
            ];
        } elseif ($params['request']->is(['post'])) {
            $response = $this->getData('/organisations/view/' . $params['uuid'], $params);
            if ($response->getStatusCode() == 200) {
                $result = $this->captureOrganisation($response->getJson()['Organisation']);
                if ($result) {
                    return ['success' => 1, 'message' => __('Organisation created/modified.')];
                } else {
                    return ['success' => 0, 'message' => __('Could not save the changes to the organisation.')];
                }
            } else {
                return ['success' => 0, 'message' => __('Could not fetch the remote organisation.')];
            }
        }
        throw new MethodNotAllowedException(__('Invalid http request type for the given action.'));
    }

    public function fetchSelectedOrganisationsAction(array $params): array
    {
        $ids = $params['request']->getQuery('ids');
        if ($params['request']->is(['get'])) {
            return [
                'data' => [
                    'title' => __('Fetch organisations'),
                    'description' => __('Fetch and create/update the selected {0} organisations from MISP?', count($ids)),
                    'submit' => [
                        'action' => $params['request']->getParam('action')
                    ],
                    'url' => ['controller' => 'localTools', 'action' => 'action', $params['connection']['id'], 'fetchSelectedOrganisationsAction']
                ]
            ];
        } elseif ($params['request']->is(['post'])) {
            $successes = 0;
            $errors = 0;
            foreach ($ids as $id) {
                $response = $this->getData('/organisations/view/' . $id, $params);
                $result = $this->captureOrganisation($response->getJson()['Organisation']);
                if ($response->getStatusCode() == 200) {
                    $successes++;
                } else {
                    $errors++;
                }
            }
            if ($successes) {
                return ['success' => 1, 'message' => __('The fetching of organisations has succeeded. {0} organisations created/modified and {1} organisations could not be created/modified.', $successes, $errors)];
            } else {
                return ['success' => 0, 'message' => __('The fetching of organisations has failed. {0} organisations could not be created/modified.', $errors)];
            }
        }
        throw new MethodNotAllowedException(__('Invalid http request type for the given action.'));
    }

    public function fetchSelectedSharingGroupsAction(array $params): array
    {
        $ids = $params['request']->getQuery('ids');
        if ($params['request']->is(['get'])) {
            return [
                'data' => [
                    'title' => __('Fetch sharing groups'),
                    'description' => __('Fetch and create/update the selected {0} sharing groups from MISP?', count($ids)),
                    'submit' => [
                        'action' => $params['request']->getParam('action')
                    ],
                    'url' => ['controller' => 'localTools', 'action' => 'action', $params['connection']['id'], 'fetchSelectedSharingGroupsAction']
                ]
            ];
        } elseif ($params['request']->is(['post'])) {
            $successes = 0;
            $errors = 0;
            foreach ($ids as $id) {
                $response = $this->getData('/sharingGroups/view/' . $id, $params);
                $temp = $response->getJson();
                $sg = $temp['SharingGroup'];
                $sg['organisation'] = $temp['Organisation'];
                foreach ($temp['SharingGroupOrg'] as $sgo) {
                    $sg['sharing_group_orgs'][] = [
                        'extend' => $sgo['extend'],
                        'name' => $sgo['Organisation']['name'],
                        'uuid' => $sgo['Organisation']['uuid']
                    ];
                }
                $result = $this->captureSharingGroup($sg, $params['user_id']);
                if ($response->getStatusCode() == 200) {
                    $successes++;
                } else {
                    $errors++;
                }
            }
            if ($successes) {
                return ['success' => 1, 'message' => __('The fetching of organisations has succeeded. {0} organisations created/modified and {1} organisations could not be created/modified.', $successes, $errors)];
            } else {
                return ['success' => 0, 'message' => __('The fetching of organisations has failed. {0} organisations could not be created/modified.', $errors)];
            }
        }
        throw new MethodNotAllowedException(__('Invalid http request type for the given action.'));
    }

    public function pushOrganisationAction(array $params): array
    {
        if ($params['request']->is(['get'])) {
            return [
                'data' => [
                    'title' => __('Push organisation'),
                    'description' => __('Push or update organisation ({0}) on MISP.', $params['uuid']),
                    'submit' => [
                        'action' => $params['request']->getParam('action')
                    ],
                    'url' => ['controller' => 'localTools', 'action' => 'action', $params['connection']['id'], 'pushOrganisationAction', $params['uuid']]
                ]
            ];
        } elseif ($params['request']->is(['post'])) {
            $org = $this->getOrganisation($params['uuid']);
            if (empty($org)) {
                return ['success' => 0, 'message' => __('Could not find the organisation.')];
            }
            $params['body'] = json_encode($org);
            $response = $this->getData('/organisations/view/' . $params['uuid'], $params);
            if ($response->getStatusCode() == 200) {
                $response = $this->postData('/admin/organisations/edit/' . $params['uuid'], $params);
                $result = $this->captureOrganisation($response->getJson()['Organisation']);
                if ($response->getStatusCode() == 200 && $result) {
                    return ['success' => 1, 'message' => __('Organisation modified.')];
                } else {
                    return ['success' => 0, 'message' => __('Could not save the changes to the organisation.')];
                }
            } else {
                $response = $this->postData('/admin/organisations/add/', $params);
                $result = $this->captureOrganisation($response->getJson()['Organisation']);
                if ($response->getStatusCode() == 200 && $result) {
                    return ['success' => 1, 'message' => __('Organisation created.')];
                } else {
                    return ['success' => 0, 'message' => __('Could not create the organisation.')];
                }
            }
        }
        throw new MethodNotAllowedException(__('Invalid http request type for the given action.'));
    }

    public function pushOrganisationsAction(array $params): array
    {
        $orgSelectorValues = $this->getOrganisationSelectorValues();
        if ($params['request']->is(['get'])) {
            return [
                'data' => [
                    'title' => __('Push organisation'),
                    'description' => __('Push or update organisations on MISP.'),
                    'fields' => [
                        [
                            'field' => 'local',
                            'label' => __('Only organisations with users'),
                            'type' => 'checkbox'
                        ],
                        [
                            'field' => 'type',
                            'label' => __('Type'),
                            'type' => 'select',
                            'options' => $orgSelectorValues['type']

                        ],
                        [
                            'field' => 'sector',
                            'label' => __('Sector'),
                            'type' => 'select',
                            'options' => $orgSelectorValues['sector']

                        ],
                        [
                            'field' => 'nationality',
                            'label' => __('Country'),
                            'type' => 'select',
                            'options' => $orgSelectorValues['nationality']

                        ]
                    ],
                    'submit' => [
                        'action' => $params['request']->getParam('action')
                    ],
                    'url' => ['controller' => 'localTools', 'action' => 'action', $params['connection']['id'], 'pushOrganisationsAction']
                ]
            ];
        } elseif ($params['request']->is(['post'])) {
            $filters = $params['request']->getData();
            $orgs = $this->getFilteredOrganisations($filters, true);
            $created = 0;
            $modified = 0;
            $errors = 0;
            $params['softError'] = 1;
            if (empty($orgs)) {
                return ['success' => 0, 'message' => __('Could not find any organisations matching the criteria.')];
            }
            foreach ($orgs as $org) {
                $params['body'] = null;
                $response = $this->getData('/organisations/view/' . $org->uuid, $params);
                if ($response->getStatusCode() == 200) {
                    $params['body'] = json_encode($org);
                    $response = $this->postData('/admin/organisations/edit/' . $org->uuid, $params);
                    if ($response->getStatusCode() == 200) {
                        $modified++;
                    } else {
                        $errors++;
                    }
                } else {
                    $params['body'] = json_encode($org);
                    $response = $this->postData('/admin/organisations/add', $params);
                    if ($response->getStatusCode() == 200) {
                        $created++;
                    } else {
                        $errors++;
                    }
                }
            }
            if ($created || $modified) {
                return ['success' => 1, 'message' => __('Organisations created: {0}, modified: {1}, errors: {2}', $created, $modified, $errors)];
            } else {
                return ['success' => 0, 'message' => __('Organisations could not be pushed. Errors: {0}', $errors)];
            }
        }
        throw new MethodNotAllowedException(__('Invalid http request type for the given action.'));
    }

    public function pushSharingGroupsAction(array $params): array
    {
        if ($params['request']->is(['get'])) {
            return [
                'data' => [
                    'title' => __('Push sharing groups'),
                    'description' => __('Push or update sharinggroups to MISP.'),
                    'fields' => [
                        [
                            'field' => 'name',
                            'label' => __('Name'),
                            'placeholder' => __('Search for sharing groups by name. You can use the % character as a wildcard.'),
                        ],
                        [
                            'field' => 'releasability',
                            'label' => __('Releasable to'),
                            'placeholder' => __('Search for sharing groups by relesability. You can use the % character as a wildcard.'),
                        ]
                    ],
                    'submit' => [
                        'action' => $params['request']->getParam('action')
                    ],
                    'url' => ['controller' => 'localTools', 'action' => 'action', $params['connection']['id'], 'pushSharingGroupsAction']
                ]
            ];
        } elseif ($params['request']->is(['post'])) {
            $filters = $params['request']->getData();
            $sgs = $this->getFilteredSharingGroups($filters, true);
            $created = 0;
            $modified = 0;
            $errors = 0;
            $params['softError'] = 1;
            if (empty($sgs)) {
                return ['success' => 0, 'message' => __('Could not find any sharing groups matching the criteria.')];
            }
            foreach ($sgs as $sg) {
                $params['body'] = null;
                $sgToPush = [
                    'name' => $sg->name,
                    'uuid' => $sg->uuid,
                    'releasability' => $sg->releasability,
                    'description' => $sg->description,
                    'roaming' => true,
                    'organisation_uuid' => $sg->organisation->uuid,
                    'Organisation' => [
                        'uuid' => $sg->organisation->uuid,
                        'name' => $sg['organisation']['name']
                    ],
                    'SharingGroupOrg' => []
                ];
                foreach ($sg['sharing_group_orgs'] as $sgo) {
                    $sgToPush['SharingGroupOrg'][] = [
                        'extend' => $sgo['_joinData']['extend'],
                        'uuid' => $sgo['uuid'],
                        'name' => $sgo['name']
                    ];
                }
                $response = $this->getData('/sharing_groups/view/' . $sg->uuid, $params);
                if ($response->getStatusCode() == 200) {
                    $params['body'] = json_encode($sgToPush);
                    $response = $this->postData('/sharingGroups/edit/' . $sg->uuid, $params);
                    if ($response->getStatusCode() == 200) {
                        $modified++;
                    } else {
                        $errors++;
                    }
                } else {
                    $params['body'] = json_encode($sgToPush);
                    $response = $this->postData('/sharingGroups/add', $params);
                    if ($response->getStatusCode() == 200) {
                        $created++;
                    } else {
                        $errors++;
                    }
                }
            }
            if ($created || $modified) {
                return ['success' => 1, 'message' => __('Sharing groups created: {0}, modified: {1}, errors: {2}', $created, $modified, $errors)];
            } else {
                return ['success' => 0, 'message' => __('Sharing groups could not be pushed. Errors: {0}', $errors)];
            }
        }
        throw new MethodNotAllowedException(__('Invalid http request type for the given action.'));
    }

    public function fetchSharingGroupAction(array $params): array
    {
        if ($params['request']->is(['get'])) {
            return [
                'data' => [
                    'title' => __('Fetch sharing group'),
                    'description' => __('Fetch and create/update sharing group ({0}) from MISP.', $params['uuid']),
                    'submit' => [
                        'action' => $params['request']->getParam('action')
                    ],
                    'url' => ['controller' => 'localTools', 'action' => 'action', $params['connection']['id'], 'fetchSharingGroupAction', $params['uuid']]
                ]
            ];
        } elseif ($params['request']->is(['post'])) {
            $response = $this->getData('/sharing_groups/view/' . $params['uuid'], $params);
            if ($response->getStatusCode() == 200) {
                $mispSG = $response->getJson();
                $sg = [
                    'uuid' => $mispSG['SharingGroup']['uuid'],
                    'name' => $mispSG['SharingGroup']['name'],
                    'releasability' => $mispSG['SharingGroup']['releasability'],
                    'description' => $mispSG['SharingGroup']['description'],
                    'organisation' => $mispSG['Organisation'],
                    'sharing_group_orgs' => []
                ];
                foreach ($mispSG['SharingGroupOrg'] as $sgo) {
                    $sg['sharing_group_orgs'][] = [
                        'name' => $sgo['Organisation']['name'],
                        'uuid' => $sgo['Organisation']['uuid'],
                        'extend' => $sgo['extend']
                    ];
                }
                $result = $this->captureSharingGroup($sg, $params['user_id']);
                if ($result) {
                    return ['success' => 1, 'message' => __('Sharing group created/modified.')];
                } else {
                    return ['success' => 0, 'message' => __('Could not save the changes to the sharing group.')];
                }
            } else {
                return ['success' => 0, 'message' => __('Could not fetch the remote sharing group.')];
            }
        }
        throw new MethodNotAllowedException(__('Invalid http request type for the given action.'));
    }

    public function modifySettingAction(array $params): array
    {
        if ($params['request']->is(['get'])) {
            $response = $this->getData('/servers/getSetting/' . $params['setting'], $params);
            if ($response->getStatusCode() != 200) {
                throw new NotFoundException(__('Setting could not be fetched from the remote.'));
            }
            $response = $response->getJson();
            $types = [
                'string' => 'text',
                'boolean' => 'checkbox',
                'numeric' => 'number'
            ];
            if (!empty($response['options'])) {
                $fields = [
                    [
                        'field' => 'value',
                        'label' => __('Value'),
                        'default' => h($response['value']),
                        'type' => 'dropdown',
                        'options' => $response['options']
                    ]
                ];
            } else {
                $fields = [
                    [
                        'field' => 'value',
                        'label' => __('Value'),
                        'default' => h($response['value']),
                        'type' => $types[$response['type']]
                    ]
                ];
            }
            return [
                'data' => [
                    'title' => __('Modify server setting'),
                    'description' => __('Modify setting ({0}) on selected MISP instance(s).', $params['setting']),
                    'fields' => $fields,
                    'submit' => [
                        'action' => $params['request']->getParam('action')
                    ],
                    'url' => ['controller' => 'localTools', 'action' => 'action', $params['connection']['id'], 'modifySettingAction', $params['setting']]
                ]
            ];
        } elseif ($params['request']->is(['post'])) {
            $params['body'] = ['value' => $params['value']];
            $response = $this->postData('/servers/serverSettingsEdit/' . $params['setting'], $params);
            if ($response->getStatusCode() == 200) {
                return ['success' => 1, 'message' => __('Setting saved.')];
            } else {
                return ['success' => 0, 'message' => __('Could not update.')];
            }
        }
        throw new MethodNotAllowedException(__('Invalid http request type for the given action.'));
    }

    public function batchAPIAction(array $params): array
    {
        if ($params['request']->is(['get'])) {
            return [
                'data' => [
                    'title' => __('Execute API Request'),
                    'description' => __('Perform an API Request on the list of selected connections'),
                    'fields' => [
                        [
                            'field' => 'connection_ids',
                            'type' => 'hidden',
                            'value' => json_encode($params['connection_ids'])
                        ],
                        [
                            'field' => 'method',
                            'label' => __('Method'),
                            'type' => 'dropdown',
                            'options' => ['GET' => 'GET', 'POST' => 'POST']
                        ],
                        [
                            'field' => 'url',
                            'label' => __('Relative URL'),
                            'type' => 'text',
                        ],
                        [
                            'field' => 'body',
                            'label' => __('POST Body'),
                            'type' => 'codemirror',
                        ],
                    ],
                    'submit' => [
                        'action' => $params['request']->getParam('action')
                    ],
                    'url' => ['controller' => 'localTools', 'action' => 'batchAction', 'batchAPIAction']
                ]
            ];
        } else if ($params['request']->is(['post'])) {
            if ($params['method'] == 'GET') {
                $response = $this->getData($params['url'], $params);
            } else {
                $response = $this->postData($params['url'], $params);
            }
            if ($response->getStatusCode() == 200) {
                return ['success' => 1, 'message' => __('API query successful'), 'data' => $response->getJson()];
            } else {
                return ['success' => 0, 'message' => __('API query failed'), 'data' => $response->getJson()];
            }
        }
        throw new MethodNotAllowedException(__('Invalid http request type for the given action.'));
    }

    public function initiateConnection(array $params): array
    {
        $params['connection_settings'] = json_decode($params['connection']['settings'], true);
        $params['misp_organisation'] = $this->getSetOrg($params);
        $params['sync_user'] = $this->createSyncUser($params, true);
        return [
            'email' => $params['sync_user']['email'],
            'user_id' => $params['sync_user']['id'],
            'authkey' => $params['sync_user']['authkey'],
            'url' => $params['connection_settings']['url'],
        ];
    }

    public function acceptConnection(array $params): array
    {
        $params['sync_user_enabled'] = true;
        $params['connection_settings'] = json_decode($params['connection']['settings'], true);
        $params['misp_organisation'] = $this->getSetOrg($params);
        $params['sync_user'] = $this->createSyncUser($params, false);
        $serverParams = $params;
        $serverParams['body'] = [
            'authkey' => $params['remote_tool_data']['authkey'],
            'url' => $params['remote_tool_data']['url'],
            'name' => !empty($params['remote_tool_data']['tool_name']) ? $params['remote_tool_data']['tool_name'] : sprintf('MISP for %s', $params['remote_tool_data']['url']),
            'remote_org_id' => $params['misp_organisation']['id']
        ];
        $params['sync_connection'] = $this->addServer($serverParams);
        return [
            'email' => $params['sync_user']['email'],
            'authkey' => $params['sync_user']['authkey'],
            'url' => $params['connection_settings']['url'],
            'reflected_user_id' => $params['remote_tool_data']['user_id'] // request initiator Cerebrate to enable the MISP user
        ];
    }

    public function finaliseConnection(array $params): bool
    {
        $params['misp_organisation'] = $this->getSetOrg($params);
        $user = $this->enableUser($params, intval($params['remote_tool_data']['reflected_user_id']));
        $serverParams = $params;
        $serverParams['body'] = [
            'authkey' => $params['remote_tool_data']['authkey'],
            'url' => $params['remote_tool_data']['url'],
            'name' => !empty($params['remote_tool_data']['tool_name']) ? $params['remote_tool_data']['tool_name'] : sprintf('MISP for %s', $params['remote_tool_data']['url']),
            'remote_org_id' => $params['misp_organisation']['id']
        ];
        $params['sync_connection'] = $this->addServer($serverParams);
        return true;
    }

    private function getSetOrg(array $params): array
    {
        $params['softError'] = 1;
        $response = $this->getData('/organisations/view/' . $params['remote_org']['uuid'], $params);
        if ($response->isOk()) {
            $organisation = $response->getJson()['Organisation'];
            if (!$organisation['local']) {
                $organisation['local'] = 1;
                $params['body'] = $organisation;
                $response = $this->postData('/admin/organisations/edit/' . $organisation['id'], $params);
                if (!$response->isOk()) {
                    throw new MethodNotAllowedException(__('Could not update the organisation in MISP.'));
                }
            }
        } else {
            $params['body'] = [
                'uuid' => $params['remote_org']['uuid'],
                'name' => $params['remote_org']['name'],
                'local' => 1
            ];
            $response = $this->postData('/admin/organisations/add', $params);
            if ($response->isOk()) {
                $organisation = $response->getJson()['Organisation'];
            } else {
                throw new MethodNotAllowedException(__('Could not create the organisation in MISP.'));
            }
        }
        return $organisation;
    }

    private function createSyncUser(array $params, $disabled=true): array
    {
        $params['softError'] = 1;
        $user = [
            'email' => 'sync_%s@' . parse_url($params['remote_cerebrate']['url'])['host'],
            'org_id' => $params['misp_organisation']['id'],
            'role_id' => empty($params['connection_settings']['role_id']) ? 5 : $params['connection_settings']['role_id'],
            'disabled' => $disabled,
            'change_pw' => 0,
            'termsaccepted' => 1
        ];
        return $this->createUser($user, $params);
    }

    private function enableUser(array $params, int $userID): array
    {
        $params['softError'] = 1;
        $user = [
            'disabled' => false,
        ];
        return $this->updateUser($userID, $user, $params);
    }

    private function addServer(array $params): array
    {
        if (
            empty($params['body']['authkey']) ||
            empty($params['body']['url']) ||
            empty($params['body']['remote_org_id']) ||
            empty($params['body']['name'])
        ) {
            throw new MethodNotAllowedException(__('Required data missing from the sync connection object. The following fields are required: [name, url, authkey, org_id].'));
        }
        $response = $this->postData('/servers/add', $params);
        if (!$response->isOk()) {
            throw new MethodNotAllowedException(__('Could not add Server in MISP.'));
        }
        return $response->getJson()['Server'];
    }

    private function createUser(array $user, array $params): array
    {
        if (strpos($user['email'], '%s') !== false) {
            $user['email'] = sprintf(
                $user['email'],
                \Cake\Utility\Security::randomString(8)
            );
        }
        $params['body'] = $user;
        $response = $this->postData('/admin/users/add', $params);
        if (!$response->isOk()) {
            throw new MethodNotAllowedException(__('Could not add the user in MISP.'));
        }
        return $response->getJson()['User'];
    }

    private function updateUser(int $userID, array $user, array $params): array
    {
        $params['body'] = $user;
        $response = $this->postData(sprintf('/admin/users/edit/%s', $userID), $params);
        if (!$response->isOk()) {
            throw new MethodNotAllowedException(__('Could not edit the user in MISP.'));
        }
        return $response->getJson()['User'];
    }
}

 ?>
