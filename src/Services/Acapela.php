<?php

namespace ChrisJP\TTS\Services;

use ChrisJP\TTS\Services\Service;
use ChrisJP\TTS\Request;
use ChrisJP\TTS\ReturnObjectTrait;

/**
 * Acapela
 * 
 * Doesn't use their actual API. Simulates a request coming from the demo page on their website.
 */
class Acapela implements Service 
{

    use ReturnObjectTrait;

    const baseURL = 'https://voice.reverso.net/RestPronunciation.svc/v1/output=json';

    const demoSite = 'https://voice.reverso.net/test/';

    /**
     * Full name of this service.
     *
     * @var string
     */
    private string $name = 'Acapela';

    /**
     * Short name of this service.
     * Will be used in audio filenames so keep it short and no weird characters/spaces etc.
     *
     * @var string
     */
    private string $shortName = 'Acapela';

    /**
     * The default voice that will be used if one is not set.
     *
     * @var string
     */
    private string $defaultVoice = 'sharon22k';

    /**
     * Returns the full name of this service.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Returns the short name of this service.
     *
     * @return string
     */
    public function getShortName(): string
    {
        return $this->shortName;
    }

    /**
     * Returns the default voice ID to be used with this service.
     *
     * @return string
     */
    public function getDefaultVoice(): string
    {
        return $this->defaultVoice;
    }

    /**
     * Constructs the data required to be sent to the API and calls sendRequest()
     * Returns an object containing the audio URL and various other data.
     *
     * @param string $voice
     * @param string $text
     * @return object
     */
    public function requestTTS(string $voice, string $text): object
    {
        // Before we can request Acapela TTS we need to acquire valid session variables.
        // For some reason, Acapela outputs all the required data as a JavaScript variable
        // here: https://www.acapela-group.com/www/static/website/demoOptionsDef_voicedemo.php
        // Trivially, we can simply fetch the contents and extract the JSON from the variable.
        // NOTE: accessing that URL in your browser will result in seeing a different json_service_url
        //       where few of the voices will work.

        // Headers to pretend we're making a request from the demo website
        $headers = [
            'Host: voice.reverso.net',
            'Accept: */*',
            'Accept-Language: en-US,en;q=0.9',
            'Accept-Encoding: identity;q=1, *;q=',
            'Origin: https://voice.reverso.net',
            'Cookie: __cf_bm:owDj8AxPm7qVhRJoXVyC8zu.GxlGIJVe9H2_FH5bW7c-1748279727-1.0.1.1-byBYth0P9oO1LL3zeiVbc_qz_EI75HXjQHFI9TIZybJdVn3pqPvt3WtnVyi3CnWo3PvvT5hbpQ8cqpv7DPZirsqcE5F5FfkLH1p2ANtvxLI',
            'DNT: 1',
            'Priority: u=1, i',
            'Referer: https://www.reverso.com/',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:107.0) Gecko/20100101 Firefox/107.0',
            'Sec-Fetch-Dest: empty',
            'Sec-Fetch-Mode: cors',
            'Sec-Fetch-Site: same-site',
            'Connection: keep-alive'                            
        ];

        $requestStep1 = new Request('https://voice.reverso.net/api/v1/tts/$voice');
        $requestStep1->sendRequest('', false, $headers);
        $responseStep1 = $requestStep1->getResponse();
        
        // Remove the JavaScript 'var' and terminating semi-colon so we're left with a valid JSON string
        $vaasJSON = str_replace(['var vaasOptions = ', '};'], ['', '}'], $responseStep1);
        $vaasOptions = json_decode($vaasJSON);

        $params = [
            'cl_login'      => $vaasOptions->login,
            'cl_app'        => $vaasOptions->app,
            'session_start' => $vaasOptions->session->start,
            'session_time'  => $vaasOptions->session->time,
            'session_key'   => $vaasOptions->session->key,
            'req_voice'     => $voice,
            'req_text'      => $text,
        ];

        // TODO: may want to use $vaasOptions->json_service_url rather than our hardcoded baseURL constant in case they ever change it?
        $request = new Request($this::baseURL);
        $request->sendRequest($params, true, $headers);

        $response = $request->getResponse();
        $curlInfo = $request->getInfo();

        return $this->handleResponse($response, $voice, $text, $curlInfo);
    }

    /**
     * Handles the response we got from the cURL request made in sendRequest()
     * Creates and returns an object containing the audio URL and any other data we require.
     *
     * @param $response
     * @param string $voice
     * @param string $text
     * @param array $info
     * @return object
     */
    private function handleResponse($response, string $voice, string $text, array $curlInfo = []): object
    {
        $success = false;
        $audioUrl = null;
        $errorMessage = null;

        // Response should be JSON
        $responseObj = json_decode($response);

        if ($responseObj->res === 'NOK') {
            $errorMessage = $responseObj->err_code . ': ' . urldecode($responseObj->err_msg);
            $errorMessage .= '; URL attempted: ' . $curlInfo['url'];
        }
        else if ($responseObj->res === 'OK') {
            $success = true;
            $audioUrl = $responseObj->snd_url;
        }

        $returnData = $this->buildReturnObject($success, $audioUrl, null, $curlInfo, $errorMessage, $response);

        return $returnData;
    }
}
