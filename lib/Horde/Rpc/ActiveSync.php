<?php

/**
 * Copyright 2009-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Michael J Rubinsky <mrubinsk@horde.org>
 * @author   Torben Dannhauer <torben@dannhauer.de>
 *
 * @category Horde
 * @package  Rpc
 */
class Horde_Rpc_ActiveSync extends Horde_Rpc
{
    /**
     * Holds the request's GET variables
     *
     * @var array
     */
    protected $_get = [];

    /**
     * The ActiveSync server object
     *
     * @var Horde_ActiveSync
     */
    protected $_server;

    /**
     * Content type header to send in response.
     *
     * @var string
     */
    protected $_contentType = 'application/vnd.ms-sync.wbxml';

    /**
     * Whether the current request streams its response body to the client
     * while the handler is still running (Sync command only, opt-in via the
     * 'streaming' parameter).
     *
     * @var boolean
     */
    protected $_streaming = false;

    /**
     * Constructor.
     *
     * @param Horde_Controller_Request_Http  The request object.
     *
     * @param array $params  A hash containing configuration parameters:
     *   - server: (Horde_ActiveSync) The ActiveSync server object.
     *             DEFAULT: none, REQUIRED
     *   - streaming: (boolean) Stream Sync response bodies to the client
     *                (chunked transfer-encoding) instead of buffering the
     *                full response and sending it with Content-Length.
     *                DEFAULT: false
     */
    public function __construct(Horde_Controller_Request_Http $request, array $params = [])
    {
        parent::__construct($request, $params);
        // Use the server's getGetVars() method since they might be transmitted
        // as base64 encoded binary data.
        $serverVars = $request->getServerVars();
        $this->_get = $params['server']->getGetVars();
        if ($request->getMethod() == 'POST'
            && (((empty($this->_get['Cmd']) || empty($this->_get['DeviceId'])
              || empty($this->_get['DeviceType'])) && empty($serverVars['QUERY_STRING']))
              && stripos($serverVars['REQUEST_URI'], 'autodiscover/autodiscover') === false)) {

            $this->_logger->err('Missing required parameters.');
            throw new Horde_Rpc_Exception('Your device requested the ActiveSync URL wihtout required parameters.');
        }
        $this->_server = $params['server'];
    }

    /**
     * Returns the Content-Type of the response.
     *
     * @return string  The MIME Content-Type of the RPC response.
     */
    public function getResponseContentType()
    {
        return $this->_contentType;
    }

    /**
     * Horde_ActiveSync will read the input stream directly, do not access
     * it here.
     *
     * @see Horde_Rpc#getInput()
     */
    public function getInput()
    {
        return null;
    }

    /**
     * Sends an RPC request to the server and returns the result.
     *
     * @param string $request  PHP input stream (ignored).
     */
    public function getResponse($request)
    {
        $serverVars = $this->_request->getServerVars();

        /* Stream Sync responses so WBXML bytes reach the client while the
         * handler is still assembling messages. Some clients (Gmail Android)
         * abort after ~30s without response body bytes and then never
         * recover. Without a Content-Length header the webserver applies
         * chunked transfer-encoding. All other commands keep the buffered
         * Content-Length path (see Bug #12486 for GetAttachment). */
        $this->_streaming = $this->_shouldStreamResponse($serverVars);

        if ($this->_streaming) {
            // Compression would buffer the body and defeat streaming.
            @ini_set('zlib.output_compression', 0);
            // Discard pre-existing output buffers: flushing them could leak
            // stray bytes (whitespace, notices) ahead of the WBXML stream.
            // Non-removable buffers return false; stop instead of looping.
            while (ob_get_level()) {
                if (!@ob_end_clean()) {
                    break;
                }
            }
            $this->_logger->debug('Horde_Rpc_ActiveSync: streaming response body for Sync.');
        } else {
            ob_start(null, 1048576);
        }
        switch ($serverVars['REQUEST_METHOD']) {
            case 'OPTIONS':
            case 'GET':
                if ($serverVars['REQUEST_METHOD'] == 'GET'
                    && (!empty($this->_get['Cmd']) && $this->_get['Cmd'] != 'OPTIONS')
                    && stripos($serverVars['REQUEST_URI'], 'autodiscover/autodiscover') === false) {

                    $this->_logger->debug('Accessing ActiveSync endpoint from browser or missing required data.');
                    throw new Horde_Rpc_Exception(
                        Horde_Rpc_Translation::t('Trying to access the ActiveSync endpoint from a browser. Not Supported.')
                    );
                }
                if (stripos($serverVars['REQUEST_URI'], 'autodiscover/autodiscover') !== false) {
                    try {
                        $result = $this->_server->handleRequest('Autodiscover', null);
                        if (!$result) {
                            $this->_logger->err('Unknown error during Autodiscover.');
                            throw new Horde_Exception('Unknown Error');
                        }
                        $this->_contentType = $result;
                    } catch (Horde_Exception_AuthenticationFailure $e) {
                        $this->_sendAuthenticationFailedHeaders($e);
                        exit;
                    } catch (Horde_Exception $e) {
                        $this->_handleError($e);
                    }
                    break;
                }

                $this->_logger->debug('Horde_Rpc_ActiveSync::getResponse() starting for OPTIONS');
                try {
                    if (!$this->_server->handleRequest('Options', null)) {
                        throw new Horde_Exception('Unknown Error');
                    }
                } catch (Horde_Exception_AuthenticationFailure $e) {
                    $this->_sendAuthenticationFailedHeaders($e);
                    exit;
                } catch (Horde_Exception $e) {
                    $this->_handleError($e);
                }
                break;

            case 'POST':
                // Autodiscover Request
                if (stripos($serverVars['REQUEST_URI'], 'autodiscover/autodiscover.xml') !== false) {
                    $this->_get['Cmd'] = 'Autodiscover';
                    $this->_get['DeviceId'] = null;
                }

                $this->_logger->debug('Horde_Rpc_ActiveSync::getResponse() starting for ' . $this->_get['Cmd']);

                try {
                    $ret = $this->_server->handleRequest($this->_get['Cmd'], $this->_get['DeviceId']);
                    if ($ret === false) {
                        throw new Horde_Rpc_Exception(sprintf(
                            'Received FALSE while handling %s command.',
                            $this->_get['Cmd']
                        ));
                    } elseif ($ret !== true) {
                        $this->_contentType = $ret;
                    }
                } catch (Horde_ActiveSync_Exception_InvalidRequest $e) {
                    $this->_logger->err(sprintf(
                        'Returning HTTP 400 while handling %s command. Error is: %s',
                        $this->_get['Cmd'],
                        $e->getMessage()
                    ));
                    $this->_handleError($e);
                    // When streaming, body bytes may already be on the wire;
                    // the status line can no longer be changed.
                    if (!headers_sent()) {
                        header('HTTP/1.1 400 Invalid Request');
                    }
                    exit;
                } catch (Horde_Exception_AuthenticationFailure $e) {
                    $this->_sendAuthenticationFailedHeaders($e);
                    exit;
                } catch (Horde_Exception $e) {
                    $this->_logger->err(sprintf(
                        'Returning HTTP 500 while handling %s command. Error is: %s',
                        $this->_get['Cmd'],
                        $e->getMessage()
                    ));
                    $this->_handleError($e);
                    // When streaming, body bytes may already be on the wire;
                    // the status line can no longer be changed.
                    if (!headers_sent()) {
                        header('HTTP/1.1 500');
                    }
                    exit;
                }
                break;
        }
    }

    /**
     * Override the authorize method and always return true. The ActiveSync
     * server classes handle authentication directly since we need complete
     * control over what responses are sent.
     *
     * @return boolean
     */
    public function authorize()
    {
        return true;
    }

    /**
     *
     * @see Horde_Rpc#sendOutput($output)
     */
    public function sendOutput($output)
    {
        if ($this->_streaming) {
            // Streaming Sync: the handler already flushed the body to the
            // client; nothing is buffered here. Push any remaining SAPI
            // buffer and finish the (chunked) response.
            flush();
            return;
        }

        // Unfortunately, even though we can stream the data to the client
        // with a chunked encoding, using chunked encoding also breaks the
        // progress bar on the PDA. So we de-chunk here and just output a
        // content-length header and send it as a 'normal' packet. If the output
        // packet exceeds 1MB (see ob_start) then it will be sent as a chunked
        // packet anyway because PHP will have to flush the buffer.
        $len = ob_get_length();
        $data = ob_get_contents();
        ob_end_clean();

        if (!headers_sent()) {
            header('Content-Length: ' . $len);
            header('Content-Type: ' . $this->_contentType);
            flush();
            echo $data;
        } else {
            flush();
            sleep(2);
            $this->_logger->debug('Output ' . $len . ' Bytes of data found in content buffer since output started');
            echo $data;
        }
    }

    /**
     * Should the response body be streamed to the client?
     *
     * Only Sync POST requests stream, and only when enabled via the
     * 'streaming' parameter. All other commands (GetAttachment,
     * ItemOperations, ...) keep the buffered Content-Length response.
     *
     * @param array $serverVars  The request's server variables.
     *
     * @return boolean
     */
    protected function _shouldStreamResponse($serverVars)
    {
        return !empty($this->_params['streaming'])
            && $serverVars['REQUEST_METHOD'] == 'POST'
            && !empty($this->_get['Cmd'])
            && $this->_get['Cmd'] == 'Sync';
    }

    /**
     * Output exception information to the logger.
     *
     * @param Exception $e  The exception
     *
     * @throws Horde_Rpc_Exception $e
     */
    protected function _handleError($e)
    {
        $m = $e->getMessage();
        // No output buffer is active when streaming.
        $buffer = ob_get_level() ? ob_get_clean() : '';

        $this->_logger->err('Error in communicating with ActiveSync server: ' . $m);
        $b = new Horde_Support_Backtrace($e);
        $this->_logger->err((string) $b);
        $this->_logger->err('Buffer contents: ' . $buffer);

    }

    /**
     * Send 401 Unauthorized headers.
     *
     * @param Horde_Exception_AuthenticationFailure
     */
    protected function _sendAuthenticationFailedHeaders($e)
    {
        switch ($e->getCode()) {
            case constant('Horde_ActiveSync_Status::SERVER_ERROR_RETRY'):
                $this->_logger->warn('Authentication server unavailable, sending 503 response.');
                header('HTTP/1.1 503 Unavailable');
                break;
            case Horde_ActiveSync_Status::SYNC_NOT_ALLOWED:
            case Horde_ActiveSync_Status::DEVICE_BLOCKED_FOR_USER:
                $this->_logger->notice('Sending HTTP 403 Forbidden header response.');
                header('HTTP/1.1 403 Forbidden');
                break;
            default:
                $this->_logger->notice('Sending HTTP 401 Unauthorized header response.');
                header('HTTP/1.1 401 Unauthorized');
                header('WWW-Authenticate: Basic realm="Horde ActiveSync"');
        }
    }
}
