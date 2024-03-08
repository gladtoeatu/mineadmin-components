<?php

namespace Mine\Aspect;

use Hyperf\Di\Annotation\Aspect;
use Hyperf\Di\Aop\AbstractAspect;
use Hyperf\Di\Aop\ProceedingJoinPoint;

/**
 * Class ConfigCryptAspect.
 */
#[Aspect]
class ConfigCryptAspect extends AbstractAspect
{
    public array $classes = [
        'Hyperf\Config\ConfigFactory::__invoke',
    ];

    private ?bool $enable = null;
    private ?string $key = null;
    private ?string $iv = null;

    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        $result = $proceedingJoinPoint->process();
        $this->processConfig($result);
        return $result;
    }

    private function processConfig($result)
    {
        $config = (array) $result;
        $config = array_shift($config);

        foreach($config as $key => $value) {
            if ($key != 'mineadmin') {
                if (is_array($value)) {
                    $result->set($key, $this->processDecryption($result, $value));
                }
            }
        }
    }



    private function processDecryption($result, $config): array
    {
        foreach($config as $key => $value) {
            if (is_array($value)) {
                $config[$key] = $this->processDecryption($result, $value);
            } else {
                if (is_string($value)) {
                    if (preg_match('#ENC\((.*?)\)#is', $value, $matches)) {
                        if (is_null($this->enable)) {
                            $this->enable = $result->get('mineadmin.config_encryption_key', false);
                        }
                        if (is_null($this->key)) {
                            $this->key = $result->get('mineadmin.config_encryption_key', '');
                            if (!empty($this->key)) {
                                $this->key = @base64_decode($this->key);
                            }
                        }

                        if (is_null($this->key)) {
                            $this->key = $result->get('mineadmin.config_encryption_key', '');
                            if (!empty($this->key)) {
                                $this->key = @base64_decode($this->key);
                            }
                        }

                        if (is_null($this->iv)) {
                            $this->iv = $result->get('mineadmin.config_encryption_iv', '');
                            if (!empty($this->iv)) {
                                $this->iv = @base64_decode($this->iv);
                            }
                        }

                        $value = @openssl_decrypt($matches[1], 'AES-128-CBC', $this->key, 0, $this->iv);
                        if (empty($value)) {
                            $value = $matches[1];
                        }
                        $config[$key] = $value;
                    }
                }
            }
        }
        return $config;
    }
}