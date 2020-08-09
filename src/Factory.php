<?php

declare(strict_types=1);

namespace Chiron\Factory;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Chiron\Invoker\Exception\CannotResolveException;
use Chiron\Invoker\Exception\InvocationException;
use Chiron\Invoker\Exception\NotCallableException;
use Chiron\Reflection\ReflectionCallable;
use Chiron\Reflection\ReflectionCallable2;
use Chiron\Reflection\Reflection;
use Chiron\Reflection\Resolver;
use ReflectionObject;
use ReflectionClass;
use ReflectionFunction;
use ReflectionParameter;
use Closure;
use RuntimeException;
use ReflectionFunctionAbstract;
use InvalidArgumentException;
use Throwable;

use ReflectionMethod;
use ReflectionException;


//https://github.com/rdlowrey/auryn/blob/master/lib/Injector.php#L237
//https://github.com/yiisoft/injector/blob/master/src/Injector.php

final class Factory
{
    /** ContainerInterface */
    private $container;

    /** Resolver */
    private $resolver;

    /**
     * Invoker constructor.
     *
     * @param $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->resolver = new Resolver($container);
    }

    // TODO : améliorer le code regarder ici   =>   https://github.com/illuminate/container/blob/master/Container.php#L778
    // TODO : améliorer le code et regarder ici => https://github.com/thephpleague/container/blob/68c148e932ef9959af371590940b4217549b5b65/src/Definition/Definition.php#L225
    // TODO : attention on ne gére pas les alias, alors que cela pourrait servir si on veut builder une classe en utilisant l'alias qui est présent dans le container. Réfléchir si ce cas peut arriver.
    // TODO : renommer en buildClass() ????
    // TODO : améliorer le Circular exception avec le code : https://github.com/symfony/dependency-injection/blob/master/Container.php#L236
    // TODO : renommer la fonction en "make()"
    // TODO : il n'y a pas un risque de références circulaires si on appel directement cette méthode qui est public.
    public function build(string $className, array $arguments = [])
    {
        $arguments = $this->resolveArguments($arguments);

        $class = $this->reflectClass($className);

        // https://github.com/spiral/core/blob/02580dff7f1fcbc5e74caa1f78ea84c0e4c0d92e/src/Container.php#L534
        // https://github.com/spiral/core/blob/02580dff7f1fcbc5e74caa1f78ea84c0e4c0d92e/src/Container.php#L551
        // https://github.com/spiral/core/blob/02580dff7f1fcbc5e74caa1f78ea84c0e4c0d92e/src/Container.php#L558
        // TODO : améliorer ce bout de code, on fait 2 fois un new class, alors qu'on pourrait en faire qu'un !!! https://github.com/illuminate/container/blob/master/Container.php#L815
        if ($constructor = $class->getConstructor()) {
            $arguments = $this->resolver->resolveArguments($constructor, $arguments);

            return new $className(...$arguments);
        }

        //$reflection->newInstanceArgs($resolved);
        return new $className();
    }

    // TODO : ajouter la signature dans l'interface
    // TODO : regarder aussi ici : https://github.com/mrferos/di/blob/master/src/Definition/AbstractDefinition.php#L75
    // TODO : regarder ici pour utiliser le arobase @    https://github.com/slince/di/blob/master/DefinitionResolver.php#L210
    // TODO : améliorer le resolve avec la gestion des classes "Raw" et "Reference" =>   https://github.com/thephpleague/container/blob/91a751faabb5e3f5e307d571e23d8aacc4acde88/src/Argument/ArgumentResolverTrait.php#L17
    // TODO : vérifier pourquoi c'est une méthode "public" et non pas private !!!!
    private function resolveArguments(array $arguments): array
    {
        foreach ($arguments as &$arg) {
            if (! is_string($arg)) {
                continue;
            }

            //if (! is_null($this->container) && $this->container->has($arg)) {
            if ($this->container->has($arg)) {
                $arg = $this->container->get($arg);

                continue;
            }
        }

        return $arguments;
    }

    private function reflectClass(string $className): ReflectionClass
    {
        if (! class_exists($className)) {
            // TODO  : on devrait pas renvoyer une ContainerException ????
            throw new InvalidArgumentException("Entry '{$className}' cannot be resolved");
        }

        // TODO : vérifier que le constructeur est public !!!! => https://github.com/PHP-DI/PHP-DI/blob/cdcf21d2a8a60605e81ec269342d48b544d0dfc7/src/Definition/Source/ReflectionBasedAutowiring.php#L31
        // TODO : déplacer ce bout de code dans une méthode "reflectClass()"
        $class = new ReflectionClass($className);

        // TODO : ajouter une gestion des exceptions circulaires.
        // TODO : améliorer la gestion des classes non instanciables => https://github.com/illuminate/container/blob/master/Container.php#L1001

        // Prevent error if you try to instanciate an abstract class or a class with a private constructor.
        if (! $class->isInstantiable()) {
            throw new InvalidArgumentException(sprintf(
                'Entry "%s" cannot be resolved: the class is not instantiable',
                $className
            ));
        }

        return $class;
    }


}
