<?php
/**
 * Consulta a API pública ViaCEP para autocompletar o endereço (RF02, regra 4).
 * Se a API falhar (sem internet, timeout, CEP não encontrado), retorna null
 * e o front-end deve liberar o preenchimento manual — não é um erro fatal.
 */

class CepService
{
    public static function consultar(string $cep): ?array
    {
        $cep = preg_replace('/\D/', '', $cep);
        if (strlen($cep) !== 8) {
            return null;
        }

        $url = "https://viacep.com.br/ws/{$cep}/json/";

        $contexto = stream_context_create(['http' => ['timeout' => 3]]);
        $resposta = @file_get_contents($url, false, $contexto);

        if ($resposta === false) {
            return null; // sem conexão / timeout -> fallback manual no front-end
        }

        $dados = json_decode($resposta, true);

        if (!is_array($dados) || isset($dados['erro'])) {
            return null; // CEP inexistente
        }

        return [
            'logradouro' => $dados['logradouro'] ?? '',
            'bairro'     => $dados['bairro'] ?? '',
            'cidade'     => $dados['localidade'] ?? '',
            'uf'         => $dados['uf'] ?? '',
        ];
    }
}
