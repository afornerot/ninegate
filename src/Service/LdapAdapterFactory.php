<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Ldap\Adapter\AdapterInterface;
use Symfony\Component\Ldap\Adapter\ExtLdap\Adapter;

class LdapAdapterFactory
{
    public static function create(ParameterBagInterface $parameterBag): AdapterInterface
    {
        $useTls = (bool) $parameterBag->get('ldapTls');

        return new Adapter([
            'host' => (string) $parameterBag->get('ldapHost'),
            'port' => (int) $parameterBag->get('ldapPort'),
            'encryption' => $useTls ? 'tls' : 'none',
            'options' => [
                'protocol_version' => 3,
                'referrals' => false,
                // Sans ces timeouts, une requête sur un socket mort (slapd
                // redémarré, TCP idle fermé) bloque le worker indéfiniment.
                'network_timeout' => (int) $parameterBag->get('ldapNetworkTimeout'),
                'timeout' => (int) $parameterBag->get('ldapTimeout'),
                'timelimit' => (int) $parameterBag->get('ldapTimeout'),
            ],
        ]);
    }
}