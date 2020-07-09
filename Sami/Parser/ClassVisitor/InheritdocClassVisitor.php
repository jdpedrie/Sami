<?php

/*
 * This file is part of the Sami utility.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sami\Parser\ClassVisitor;

use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use Sami\Parser\ClassVisitorInterface;
use Sami\Parser\ParserContext;
use Sami\Reflection\ClassReflection;
use Sami\Reflection\LazyClassReflection;

class InheritdocClassVisitor implements ClassVisitorInterface
{
    private $context;
    private $parser;
    private $traverser;

    public function __construct(ParserContext $context, Parser $parser, NodeTraverser $traverser)
    {
        $this->context = $context;
        $this->parser = $parser;
        $this->traverser = $traverser;
    }

    public function visit(ClassReflection $class)
    {
        $modified = false;
        foreach ($class->getMethods() as $name => $method) {
            $parentMethod = false;
            $parent = $class->getParent();
            if ($parent instanceof LazyClassReflection) {
                $parent = $this->loadFullReflector($parent);
            }

            if ($parent) {
                $parentMethod = $parent->getMethod($name);
            }

            if (!$parentMethod) {
                foreach ($class->getInterfaces(true) as $i) {
                    if ($i instanceof LazyClassReflection) {
                        $i = $this->loadFullReflector($i);
                    }

                    if ($i && $parentMethod = $i->getMethod($name)) {
                        break;
                    }
                }
            }

            if (!$parentMethod) {
                continue;
            }

            foreach ($method->getParameters() as $name => $parameter) {
                if (!$parentParameter = $parentMethod->getParameter($name)) {
                    continue;
                }

                if ($parameter->getShortDesc() != $parentParameter->getShortDesc()) {
                    $parameter->setShortDesc($parentParameter->getShortDesc());
                    $modified = true;
                }

                if ($parameter->getHint() != $parentParameter->getRawHint()) {
                    // FIXME: should test for a raw hint from tags, not the one from PHP itself
                    $parameter->setHint($parentParameter->getRawHint());
                    $modified = true;
                }
            }

            if ($method->getHint() != $parentMethod->getRawHint()) {
                $method->setHint($parentMethod->getRawHint());
                $modified = true;
            }

            if ($method->getHintDesc() != $parentMethod->getHintDesc()) {
                $method->setHintDesc($parentMethod->getHintDesc());
                $modified = true;
            }

            if ('{@inheritdoc}' == strtolower(trim($method->getShortDesc())) || !$method->getDocComment()) {
                if ($method->getShortDesc() != $parentMethod->getShortDesc()) {
                    $method->setShortDesc($parentMethod->getShortDesc());
                    $modified = true;
                }

                if ($method->getLongDesc() != $parentMethod->getLongDesc()) {
                    $method->setLongDesc($parentMethod->getLongDesc());
                    $modified = true;
                }

                if ($method->getExceptions() != $parentMethod->getRawExceptions()) {
                    $method->setExceptions($parentMethod->getRawExceptions());
                    $modified = true;
                }
            }
        }

        return $modified;
    }

    private function loadFullReflector(LazyClassReflection $class)
    {
        $name = $class->getName();

        try {
            $src = (new \ReflectionClass($name))->getFileName();
        } catch (\ReflectionException $e) {
            return false;
        }
        
        if (empty($src)) {
            return false;
        }

        try {
            $code = file_get_contents($src);
            $nodes = $this->traverser->traverse($this->parser->parse($code));
        } catch (Error $e) {
            return false;
        }

        return $this->context->getClassFromClasses($name);
    }
}
