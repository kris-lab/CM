<?php

class CM_Janus_Factory {

    /**
     * @param array $servers
     * @return CM_Janus_Service
     */
    public function createService(array $servers) {
        $configuration = new CM_Janus_Configuration();
        foreach ($servers as $serverId => $serverConfig) {
            $configuration->addServer(new CM_Janus_Server(
                $serverId,
                $serverConfig['key'],
                $serverConfig['httpAddress'],
                $serverConfig['webSocketAddress'],
                $serverConfig['iceServerList']
            ));
        }

        $httpClient = new GuzzleHttp\Client();
        $httpApiClient = new CM_Janus_HttpApiClient($httpClient);
        $janus = new CM_Janus_Service($configuration, $httpApiClient);
        return $janus;
    }
}
