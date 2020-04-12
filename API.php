<?php

namespace Riddman\MeestExpress;

use Carbon\Carbon;
use GuzzleHttp\Client;
use RuntimeException;

class API
{
    /**
     * @var Client
     */
    protected $httpClient;

    /**
     * @var string
     */
    protected $token;

    /**
     * @var Carbon|null
     */
    protected $tokenExpire;

    /**
    * @var integer
    */
    protected $timeout = 30;

    /**
    * @var array
    *
    */

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->config['email'] = env('MEEST_EXPRESS_LOGIN', null);
        $this->config['password'] = env('MEEST_EXPRESS_PASSWORD', null);
    }

    /**
     * @return Client
     */
    public function getHttpClient(): Client
    {

        if (! $this->httpClient) {
            $this->httpClient = new Client([
                'base_uri' => 'https://api.meest.com/v3.0/openAPI/',
                'timeout'  => $this->timeout,
            ]);
        }

        return $this->httpClient;
    }

        /**
     * @return string
     */
    public function getToken(): string
    {
        if (\Cache::has('meestexpress.token')) {
            $tokenData = \Cache::get('meestexpress.token');

            if (! empty($tokenData['token'])
                && ($expire = Carbon::parse($tokenData['expire']))
                && $expire->greaterThan(Carbon::now())
            ) {
                $this->token = $tokenData['token'];
                $this->tokenExpire = $expire;
            }
        }

        if (! $this->tokenExpire || !$this->tokenExpire->greaterThan(Carbon::now())) {
            if (empty($this->config['email']) || empty($this->config['password'])) {
                throw new RuntimeException('MeestExpress: Invalid credentials');
            }

            $response = $this->getHttpClient()->post('auth', [
                'json' => [
                    'username' => $this->config['email'],
                    'password' => $this->config['password'],
                ],
                'headers' => [
                    'Content-Type' => 'application/json',
                    'accept' => 'application/json',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            $this->token = $data['result']['token'];
            $this->tokenExpire = Carbon::now()->addHours(12) ;

            \Cache::put('meestexpress.token', [
                'token' => $this->token,
                'expire' => $this->tokenExpire->toAtomString(),
            ], 60 * 12);
        }

        return $this->token;
    }

    protected function retrieveFilters($filters)
    {
        $alowedList = [
            'branchNo',
            'branchTypeID',
            'branchDescr',
            'cityID',
            'cityDescr',
            'districtID',
            'districtDescr',
            'regionID',
            'regionDescr',
        ];

        $result = [];

        foreach ($alowedList as $item) {
            if (isset($filters[$item])) {
                $result[$item] = $filters[$item];
            }
        }

        return $result;
    }


        /**
     * @return array
     * @throws \Exception
     */
    public function getBranches($filters = []): array
    {
        try {
            $citiesList = [];
            $departmentsList = [];

            $response = $this->getHttpClient()->post('branchSearch', [
                'json' => [
                    'filters' => $this->retrieveFilters($filters)
                ],
                'headers' => [
                    'Content-type' => 'application/json; charset=utf-8',
                    'Accept' => 'application/json',
                    'token' => $this->getToken(),
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return $data;
        }catch (Throwable $e) {

            if (env('APP_DEBUG') == 'true') {
                dump($e->getMessage());
            }

            return [];
        }
    }
}
