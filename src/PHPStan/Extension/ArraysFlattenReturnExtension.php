<?php
declare(strict_types=1);

namespace DR\Utils\PHPStan\Extension;

use DR\Utils\Arrays;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\ArrayType;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\IntegerType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\UnionType;

/**
 * Resolves the precise item type for {@see Arrays::flatten()}, which cannot be expressed with a native
 * (recursive) generic type: nested arrays are recursively unwrapped down to their non-array leaf values.
 */
class ArraysFlattenReturnExtension implements DynamicStaticMethodReturnTypeExtension
{
    public function getClass(): string
    {
        return Arrays::class;
    }

    public function isStaticMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'flatten';
    }

    /**
     * @inheritDoc
     */
    public function getTypeFromStaticMethodCall(MethodReflection $methodReflection, StaticCall $methodCall, Scope $scope): Type
    {
        [$items] = $methodCall->getArgs();

        $arrayType = $scope->getType($items->value);
        // @codeCoverageIgnoreStart
        if ($arrayType instanceof ArrayType === false && $arrayType instanceof ConstantArrayType === false) {
            return $arrayType;
        }
        // @codeCoverageIgnoreEnd

        $leafTypes = $this->collectLeafTypes($arrayType);

        // literal arrays are flattened into an exact tuple, preserving each leaf's precise type
        if ($arrayType instanceof ConstantArrayType) {
            return new ConstantArrayType(
                array_map(static fn (int $index): ConstantIntegerType => new ConstantIntegerType($index), array_keys($leafTypes)),
                array_values($leafTypes)
            );
        }

        $itemType = count($leafTypes) > 0 ? TypeCombinator::union(...$leafTypes) : $arrayType->getItemType();

        return new ArrayType(new IntegerType(), $itemType);
    }

    /**
     * Recursively unwraps nested arrays into an ordered list of their non-array leaf types.
     *
     * @return Type[]
     */
    private function collectLeafTypes(Type $type): array
    {
        if ($type instanceof ConstantArrayType) {
            $valueTypes = $type->getValueTypes();
        } elseif ($type instanceof ArrayType) {
            $itemType   = $type->getItemType();
            $valueTypes = $itemType instanceof UnionType ? $itemType->getTypes() : [$itemType];
        } else {
            return [$type];
        }

        $leafTypes = [];
        foreach ($valueTypes as $valueType) {
            array_push($leafTypes, ...$this->collectLeafTypes($valueType));
        }

        return $leafTypes;
    }
}
