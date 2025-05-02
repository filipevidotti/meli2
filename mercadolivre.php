<?php
/**
 * Mercado Livre API Integration Class (Adaptado para SaaS Multi-usuário - Fase 1)
 */

class MercadoLivreAPI {
    private $client_id;
    private $client_secret;
    private $redirect_uri;
    private $access_token = null;
    private $refresh_token = null;
    private $ml_user_id = null; // ID do usuário no Mercado Livre
    private $token_expires_at = null; // Timestamp de expiração
    private $api_url = 'https://api.mercadolibre.com';
    private $last_error = null;
    private $usuario_id; // ID do usuário da nossa aplicação

    /**
     * Construtor da classe.
     * Recebe as configurações específicas do usuário.
     * 
     * @param array $config Array contendo 'ml_client_id', 'ml_client_secret', 'ml_redirect_uri'.
     * @throws Exception Se a configuração estiver incompleta ou inválida.
     */
    public function __construct($config) {
        $this->usuario_id = obterUsuarioIdLogado();
        if (!$this->usuario_id) {
            throw new Exception("Erro interno: Usuário não identificado ao instanciar MercadoLivreAPI.");
        }

        // Valida configurações essenciais
        if (empty($config['ml_client_id']) || empty($config['ml_client_secret']) || empty($config['ml_redirect_uri'])) {
            throw new Exception("Configuração da API do Mercado Livre incompleta (Client ID, Secret ou Redirect URI ausentes). Verifique as configurações.");
        }
        if (filter_var($config['ml_redirect_uri'], FILTER_VALIDATE_URL) === false || parse_url($config['ml_redirect_uri'], PHP_URL_SCHEME) !== 'https') {
            throw new Exception("URL de Redirecionamento inválida ou não HTTPS. Verifique as configurações.");
        }

        $this->client_id = $config['ml_client_id'];
        $this->client_secret = $config['ml_client_secret'];
        $this->redirect_uri = $config['ml_redirect_uri'];

        // Carrega os tokens do banco de dados para este usuário
        $this->loadTokensFromDB();
    }

    /**
     * Carrega os tokens do banco de dados usando a função global.
     */
    private function loadTokensFromDB() {
        // A função carregarTokensML() já é específica do usuário logado
        $tokenData = carregarTokensML(); 

        if ($tokenData) {
            $this->access_token = $tokenData['access_token'];
            $this->refresh_token = $tokenData['refresh_token'];
            $this->token_expires_at = $tokenData['expires_at']; // Já deve ser timestamp
            $this->ml_user_id = $tokenData['ml_user_id'];
        } else {
            $this->clearLocalTokens();
        }
    }

    /**
     * Salva os tokens no banco de dados usando a função global.
     * 
     * @param array $tokenResponse Resposta da API contendo access_token, refresh_token, expires_in, user_id.
     * @return bool True em sucesso, false em erro.
     */
    private function saveTokensToDB($tokenResponse) {
        // A função salvarTokensML() já é específica do usuário logado
        $success = salvarTokensML(
            $tokenResponse['access_token'],
            $tokenResponse['refresh_token'],
            $tokenResponse['expires_in'],
            $tokenResponse['user_id'] ?? null // user_id pode não vir no refresh
        );

        if ($success) {
            // Atualiza as propriedades locais após salvar
            $this->loadTokensFromDB(); 
            return true;
        } else {
            error_log("Falha ao salvar tokens ML para usuário {$this->usuario_id} usando salvarTokensML.");
            return false;
        }
    }
    
    /**
     * Limpa as propriedades de token locais.
     */
    private function clearLocalTokens() {
        $this->access_token = null;
        $this->refresh_token = null;
        $this->ml_user_id = null;
        $this->token_expires_at = null;
    }

    /**
     * Remove os tokens do banco de dados usando a função global.
     * 
     * @return bool True em sucesso, false em erro.
     */
    public function disconnect() {
        // A função removerTokensML() já é específica do usuário logado
        if (removerTokensML()) {
            $this->clearLocalTokens();
            return true;
        } else {
            $this->last_error = "Erro ao remover tokens do banco de dados.";
            error_log($this->last_error . " para usuário {$this->usuario_id}");
            return false;
        }
    }

    /**
     * Obtém a URL de autorização do Mercado Livre.
     * 
     * @return string A URL de autorização.
     */
    public function getAuthorizationUrl() {
        $encoded_redirect_uri = urlencode($this->redirect_uri);
        // Usar .com.br para Brasil
        return "https://auth.mercadolivre.com.br/authorization?response_type=code&client_id={$this->client_id}&redirect_uri={$encoded_redirect_uri}";
    }

    /**
     * Troca o código de autorização por um access token.
     * 
     * @param string $code O código recebido no callback.
     * @return bool True em sucesso, false em erro.
     */
    public function getAccessToken($code) {
        $this->last_error = null;
        $url = "{$this->api_url}/oauth/token";
        $data = [
            'grant_type' => 'authorization_code',
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'code' => $code,
            'redirect_uri' => $this->redirect_uri
        ];

        error_log("MercadoLivreAPI::getAccessToken - Tentando troca de token para usuário {$this->usuario_id}");

        try {
            // Não usa token para esta requisição, não tenta retry
            $response = $this->executeHttpRequest($url, 'POST', $data, false, false); 
            
            if ($response && isset($response['access_token']) && isset($response['refresh_token'])) {
                if ($this->saveTokensToDB($response)) {
                    return true;
                }
                $this->last_error = "Falha ao salvar token após troca bem-sucedida.";
                return false;
            } else {
                // O erro já foi setado dentro de executeHttpRequest
                $this->last_error = "Resposta inválida ao obter access token: " . ($this->last_error ?: 'Erro desconhecido');
                return false;
            }
        } catch (Exception $e) {
            $this->last_error = "Exceção durante getAccessToken: " . $e->getMessage();
            error_log($this->last_error . " para usuário {$this->usuario_id}");
            return false;
        }
    }

    /**
     * Atualiza o access token usando o refresh token.
     * 
     * @return bool True em sucesso, false em erro.
     */
    private function refreshToken() {
        $this->last_error = null;
        if (!$this->refresh_token) {
            $this->last_error = "Não é possível atualizar: Refresh token não disponível.";
            return false;
        }

        $url = "{$this->api_url}/oauth/token";
        $data = [
            'grant_type' => 'refresh_token',
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'refresh_token' => $this->refresh_token
        ];
        
        error_log("MercadoLivreAPI::refreshToken - Tentando refresh para usuário {$this->usuario_id}");

        try {
            // Não usa token para esta requisição, não tenta retry
            $response = $this->executeHttpRequest($url, 'POST', $data, false, false); 
            
            if ($response && isset($response['access_token']) && isset($response['refresh_token'])) {
                 // A resposta do refresh pode não conter 'user_id', precisamos preservar o existente
                 $response['user_id'] = $this->ml_user_id; 
                 
                 if ($this->saveTokensToDB($response)) {
                     return true;
                 }
                 $this->last_error = "Falha ao salvar token após refresh bem-sucedido.";
                 return false;
            } else {
                $this->last_error = "Resposta inválida ao atualizar token: " . ($this->last_error ?: 'Erro desconhecido');
                // Se o refresh falhar (ex: refresh token inválido), desconecta o usuário
                error_log("Falha no refresh token para usuário {$this->usuario_id}. Desconectando... Erro: {$this->last_error}");
                $this->disconnect(); 
                return false;
            }
        } catch (Exception $e) {
            $this->last_error = "Exceção durante refreshToken: " . $e->getMessage();
            error_log($this->last_error . " para usuário {$this->usuario_id}");
            // Se o refresh falhar, desconecta o usuário
            $this->disconnect(); 
            return false;
        }
    }

    /**
     * Executa uma requisição HTTP para a API do Mercado Livre.
     * Inclui tratamento de erro e atualização automática de token.
     * 
     * @param string $endpoint O endpoint da API (ex: '/users/me').
     * @param string $method Método HTTP (GET, POST, PUT, DELETE, PATCH).
     * @param mixed $payload Dados para enviar (array para JSON ou query string).
     * @param bool $use_token Se deve incluir o token de autorização.
     * @param bool $retry_on_401 Se deve tentar atualizar o token e repetir em caso de 401.
     * @return array Resposta decodificada da API.
     * @throws Exception Em caso de erro crítico ou falha na autenticação.
     */
    public function executeApiRequest($endpoint, $method = 'GET', $payload = null, $use_token = true, $retry_on_401 = true) {
        $url = rtrim($this->api_url, '/') . '/' . ltrim($endpoint, '/');
        return $this->executeHttpRequest($url, $method, $payload, $use_token, $retry_on_401);
    }

    /**
     * Lógica interna para execução de requisições HTTP.
     */
    private function executeHttpRequest($url, $method = 'GET', $payload = null, $use_token = true, $retry_on_401 = true) {
        $this->last_error = null;
        $headers = ["Accept: application/json"];

        if ($use_token) {
            // Garante que os tokens estão carregados e válidos antes da requisição
            if (!$this->isAuthenticated(true)) { // true força a verificação/refresh
                 $this->last_error = "Autenticação necessária ou falhou antes da requisição para {$url}. " . $this->last_error;
                 throw new Exception($this->last_error);
            }
            $headers[] = "Authorization: Bearer {$this->access_token}";
        }

        $options = [
            'http' => [
                'method' => $method,
                'ignore_errors' => true, // Tratar erros com base no código de status
                // Header definido abaixo
            ]
        ];

        $request_url = $url;
        if ($payload !== null) {
            if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
                // Tratamento especial para o endpoint de token: deve ser form-urlencoded
                if (strpos($url, '/oauth/token') !== false) {
                    $headers[] = "Content-type: application/x-www-form-urlencoded";
                    $options['http']['content'] = http_build_query($payload);
                } 
                // Para outros endpoints, assume JSON se o payload for array/objeto
                elseif (is_array($payload) || is_object($payload)) {
                    $headers[] = "Content-Type: application/json";
                    $options['http']['content'] = json_encode($payload);
                } else {
                    // Fallback para payloads não-array/objeto (ex: string bruta)
                    $headers[] = "Content-type: application/x-www-form-urlencoded"; // Padrão, pode precisar de ajuste
                    $options['http']['content'] = $payload;
                }
            } elseif ($method === 'GET' && is_array($payload)) {
                $request_url .= (strpos($request_url, '?') === false ? '?' : '&') . http_build_query($payload);
            }
        }

        $options['http']['header'] = implode("\r\n", $headers);
        $context = stream_context_create($options);
        $result = @file_get_contents($request_url, false, $context); // Usar @ para suprimir avisos

        // Obter código de status dos cabeçalhos de resposta
        $status_code = 500; // Padrão
        if (isset($http_response_header[0])) {
             if (preg_match('{HTTP/\S*\s(\d{3})}', $http_response_header[0], $match)) {
                 $status_code = intval($match[1]);
             }
        }
        
        // Tratar Resposta
        if ($result === FALSE) {
            $this->last_error = "Falha na requisição HTTP para {$method} {$request_url}. file_get_contents retornou FALSE.";
            error_log($this->last_error . " para usuário {$this->usuario_id} | Cabeçalhos: " . implode("; ", $http_response_header ?? []));
            throw new Exception($this->last_error);
        }

        $response_data = json_decode($result, true);

        // Tratar erros da API (status >= 400)
        if ($status_code >= 400) {
            $error_message = $response_data['message'] ?? 'Erro desconhecido da API';
            $error_code = $response_data['error'] ?? 'unknown_error';
            $this->last_error = "Erro da API ({$status_code} {$error_code}) para {$method} {$request_url}: {$error_message}";
            error_log($this->last_error . " para usuário {$this->usuario_id} | Corpo: " . substr($result, 0, 500));

            // Tratar 401 Unauthorized - Tentar Refresh e Repetir (se habilitado)
            if ($status_code == 401 && $use_token && $retry_on_401) {
                error_log("Recebido 401, tentando refresh e retry para {$request_url} (Usuário: {$this->usuario_id})");
                if ($this->refreshToken()) {
                    error_log("Token atualizado, repetindo requisição {$request_url} (Usuário: {$this->usuario_id})");
                    // Repete a requisição SEM a flag de retry para evitar loops infinitos
                    return $this->executeHttpRequest($url, $method, $payload, $use_token, false); 
                } else {
                    // Se o refresh falhou (já desconectou e limpou tokens dentro de refreshToken)
                    $this->last_error = "Autenticação falhou: Token expirado/inválido e refresh falhou durante requisição para {$request_url}. " . $this->last_error;
                    throw new Exception($this->last_error);
                }
            }
            // Para outros erros, apenas lança a exceção
            throw new Exception($this->last_error);
        }

        // Sucesso
        return $response_data;
    }
    
    /**
     * Verifica se o token está expirado ou próximo de expirar.
     * 
     * @param int $grace_period_seconds Segundos antes da expiração para considerar como expirado.
     * @return bool
     */
    private function isTokenExpired($grace_period_seconds = 60) {
        if ($this->token_expires_at === null) {
             // Se não sabemos a expiração, é mais seguro assumir que pode estar expirado
             return true; 
        }
        return time() >= ($this->token_expires_at - $grace_period_seconds);
    }

    /**
     * Verifica se o usuário está autenticado com o Mercado Livre.
     * Tenta atualizar o token se estiver expirado.
     * 
     * @param bool $force_refresh Se deve tentar atualizar o token mesmo que não pareça expirado.
     * @return bool True se autenticado, false caso contrário.
     */
    public function isAuthenticated($force_refresh = false) {
        if (!$this->access_token) {
            return false;
        }
        
        if ($force_refresh || $this->isTokenExpired()) {
            error_log("Verificando autenticação: Token expirado ou refresh forçado para usuário {$this->usuario_id}. Tentando refresh...");
            if (!$this->refreshToken()) {
                // Se o refresh falhar, refreshToken já terá limpado os tokens e chamado disconnect()
                return false; 
            }
            // Se o refresh funcionou, o token é válido
            return true;
        }
        
        // Se não expirou e não forçou refresh, assume que está autenticado
        return true;
    }

    /**
     * Obtém informações básicas do usuário autenticado no Mercado Livre.
     * 
     * @param bool $retry_on_401 Se deve tentar refresh em caso de 401.
     * @return array|null Dados do usuário ou null em caso de erro.
     */
    public function getUserInfo($retry_on_401 = true) {
        try {
            $response = $this->executeApiRequest('/users/me', 'GET', null, true, $retry_on_401);
            // Garante que o ml_user_id local está atualizado
            if (isset($response['id']) && $response['id'] != $this->ml_user_id) {
                $this->ml_user_id = $response['id'];
                // Tenta salvar a atualização do ml_user_id no DB (best effort)
                salvarTokensML($this->access_token, $this->refresh_token, ($this->token_expires_at - time()), $this->ml_user_id);
            }
            return $response;
        } catch (Exception $e) {
            $this->last_error = "Erro ao obter informações do usuário: " . $e->getMessage();
            error_log($this->last_error . " para usuário {$this->usuario_id}");
            return null;
        }
    }

    /**
     * Obtém o último erro ocorrido.
     * 
     * @return string|null
     */
    public function getLastError() {
        return $this->last_error;
    }
    
    /**
     * Obtém o ID do usuário do Mercado Livre associado a esta conexão.
     * 
     * @return int|null
     */
    public function getMlUserId() {
        // Tenta obter via API se não tivermos localmente e estiver autenticado
        if ($this->ml_user_id === null && $this->isAuthenticated()) {
            $this->getUserInfo(); // Tenta preencher o ml_user_id
        }
        return $this->ml_user_id;
    }
}
?>
