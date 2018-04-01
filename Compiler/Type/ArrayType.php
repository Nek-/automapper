<?php

namespace Jane\AutoMapper\Compiler\Type;

use Jane\AutoMapper\Compiler\UniqueVariableScope;
use PhpParser\Node\Stmt;
use PhpParser\Node\Expr;

class ArrayType extends Type
{
    protected $itemType;

    public function __construct(Type $itemType, $type = 'array')
    {
        parent::__construct($type);

        $this->itemType = $itemType;
    }

    public function isArray()
    {
        return true;
    }

    /**
     * (@inheritDoc}.
     */
    public function getDocTypeHint($namespace)
    {
        if ($this->itemType instanceof MultipleType) {
            $typesString = [];

            foreach ($this->itemType->getTypes() as $type) {
                $typesString[] = $type->getDocTypeHint($namespace) . '[]';
            }

            return implode('|', $typesString);
        }

        return $this->itemType->getDocTypeHint($namespace) . '[]';
    }

    /**
     * (@inheritDoc}.
     */
    public function createDenormalizationStatement(UniqueVariableScope $uniqueVariableScope, Expr $input): array
    {
        $valuesVar = new Expr\Variable($uniqueVariableScope->getUniqueName('values'));
        $statements = [
            // $values = [];
            new Expr\Assign($valuesVar, $this->createArrayValueStatement()),
        ];

        $loopValueVar = new Expr\Variable($uniqueVariableScope->getUniqueName('value'));
        $loopKeyVar = $this->createLoopKeyStatement($uniqueVariableScope);

        list($subStatements, $outputExpr) = $this->itemType->createDenormalizationStatement($uniqueVariableScope, $loopValueVar);

        $loopStatements = array_merge($subStatements, [
            new Expr\Assign($this->createLoopOutputAssignement($valuesVar, $loopKeyVar), $outputExpr),
        ]);

        $statements[] = new Stmt\Foreach_($input, $loopValueVar, [
            'keyVar' => $loopKeyVar,
            'stmts' => $loopStatements,
        ]);

        return [$statements, $valuesVar];
    }

    /**
     * (@inheritDoc}.
     */
    public function createNormalizationStatement(UniqueVariableScope $uniqueVariableScope, Expr $input): array
    {
        $valuesVar = new Expr\Variable($uniqueVariableScope->getUniqueName('values'));
        $statements = [
            // $values = [];
            new Expr\Assign($valuesVar, $this->createNormalizationArrayValueStatement()),
        ];

        $loopValueVar = new Expr\Variable($uniqueVariableScope->getUniqueName('value'));
        $loopKeyVar = $this->createLoopKeyStatement($uniqueVariableScope);

        list($subStatements, $outputExpr) = $this->itemType->createNormalizationStatement($uniqueVariableScope, $loopValueVar);

        $loopStatements = array_merge($subStatements, [
            new Expr\Assign($this->createNormalizationLoopOutputAssignement($valuesVar, $loopKeyVar), $outputExpr),
        ]);

        $statements[] = new Stmt\Foreach_($input, $loopValueVar, [
            'keyVar' => $loopKeyVar,
            'stmts' => $loopStatements,
        ]);

        return [$statements, $valuesVar];
    }

    public function getTypeHint($namespace)
    {
        return 'array';
    }

    protected function createArrayValueStatement(): Expr
    {
        return new Expr\Array_();
    }

    protected function createNormalizationArrayValueStatement(): Expr
    {
        return new Expr\Array_();
    }

    protected function createLoopKeyStatement(UniqueVariableScope $uniqueVariableScope): ?Expr
    {
        return null;
    }

    protected function createLoopOutputAssignement(Expr $valuesVar, $loopKeyVar): Expr
    {
        return new Expr\ArrayDimFetch($valuesVar);
    }

    protected function createNormalizationLoopOutputAssignement(Expr $valuesVar, $loopKeyVar): Expr
    {
        return new Expr\ArrayDimFetch($valuesVar);
    }
}
