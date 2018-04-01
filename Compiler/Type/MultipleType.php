<?php

namespace Jane\AutoMapper\Compiler\Type;

use Jane\AutoMapper\Compiler\UniqueVariableScope;
use PhpParser\Node\Stmt;
use PhpParser\Node\Expr;

class MultipleType extends Type
{
    protected $types;

    public function __construct(array $types = [])
    {
        parent::__construct('mixed');

        $this->types = $types;
    }

    /**
     * Add a type.
     *
     * @param Type $type
     *
     * @return $this
     */
    public function addType(Type $type)
    {
        if ($type instanceof self) {
            foreach ($type->getTypes() as $subType) {
                $this->types[] = $subType;
            }

            return $this;
        }

        $this->types[] = $type;

        return $this;
    }

    /**
     * Return a list of types.
     *
     * @return Type[]
     */
    public function getTypes()
    {
        return $this->types;
    }

    /**
     * {@inheritdoc}
     */
    public function getDocTypeHint($namespace)
    {
        $stringTypes = array_map(function ($type) use ($namespace) {
            return $type->getDocTypeHint($namespace);
        }, $this->types);

        return implode('|', $stringTypes);
    }

    /**
     * {@inheritdoc}
     */
    public function getTypeHint($namespace)
    {
        // We have exactly two types: one null and an object
        if (2 === count($this->types)) {
            list($type1, $type2) = $this->types;

            if ($this->isOptionalType($type1)) {
                return $type2->getTypeHint($namespace);
            }

            if ($this->isOptionalType($type2)) {
                return $type1->getTypeHint($namespace);
            }
        }

        return null;
    }

    private function isOptionalType(Type $nullType)
    {
        return 'null' === $nullType->getName();
    }

    /**
     * {@inheritdoc}
     */
    public function createDenormalizationStatement(UniqueVariableScope $uniqueVariableScope, Expr $input): array
    {
        $output = new Expr\Variable($uniqueVariableScope->getUniqueName('value'));
        $statements = [
            new Expr\Assign($output, $input),
        ];

        foreach ($this->getTypes() as $type) {
            list($typeStatements, $typeOutput) = $type->createDenormalizationStatement($uniqueVariableScope, $input);

            $statements[] = new Stmt\If_(
                $type->createConditionStatement($input),
                [
                    'stmts' => array_merge(
                        $typeStatements, [
                            new Expr\Assign($output, $typeOutput),
                        ]
                    ),
                ]
            );
        }

        return [$statements, $output];
    }

    /**
     * {@inheritdoc}
     */
    public function createNormalizationStatement(UniqueVariableScope $uniqueVariableScope, Expr $input): array
    {
        $output = new Expr\Variable($uniqueVariableScope->getUniqueName('value'));
        $statements = [
            new Expr\Assign($output, $input),
        ];

        foreach ($this->getTypes() as $type) {
            list($typeStatements, $typeOutput) = $type->createNormalizationStatement($uniqueVariableScope, $input);

            $statements[] = new Stmt\If_(
                $type->createNormalizationConditionStatement($input),
                [
                    'stmts' => array_merge(
                        $typeStatements, [
                            new Expr\Assign($output, $typeOutput),
                        ]
                    ),
                ]
            );
        }

        return [$statements, $output];
    }
}
