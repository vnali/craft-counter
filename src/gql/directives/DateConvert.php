<?php

namespace vnali\counter\gql\directives;

use Craft;
use craft\gql\base\Directive;
use craft\gql\GqlEntityRegistry;
use DateTime;
use GraphQL\Language\DirectiveLocation;
use GraphQL\Type\Definition\Directive as GqlDirective;
use GraphQL\Type\Definition\FieldArgument;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use vnali\counter\helpers\DateTimeHelper;

/**
 * Date Convert GraphQL directive
 */
class DateConvert extends Directive
{
    private const DEFAULT_CALENDAR = 'gregorian';

    public static function create(): GqlDirective
    {
        if ($type = GqlEntityRegistry::getEntity(self::name())) {
            return $type;
        }

        return GqlEntityRegistry::createEntity(static::name(), new self([
            'name' => static::name(),
            'locations' => [
                DirectiveLocation::FIELD,
            ],
            'args' => [
                new FieldArgument([
                    'name' => 'calendar',
                    'type' => Type::string(),
                    'defaultValue' => 'gregorian',
                    'description' => 'The calendar to use. Currently can be `gregorian`.',
                ]),
                new FieldArgument([
                    'name' => 'format',
                    'type' => Type::string(),
                    'defaultValue' => 'yyyy/MM/dd',
                    'description' => 'The timezone.',
                ]),
                new FieldArgument([
                    'name' => 'locale',
                    'type' => Type::string(),
                    'defaultValue' => 'en_US',
                    'description' => 'The locale.',
                ]),
                new FieldArgument([
                    'name' => 'timezone',
                    'type' => Type::string(),
                    'defaultValue' => Craft::$app->getTimeZone(),
                    'description' => 'The timezone.',
                ]),
            ],
            'description' => 'Convert dates to other calendar systems',
        ]));
    }

    public static function name(): string
    {
        return 'dateConvert';
    }

    public static function apply(mixed $source, mixed $value, array $arguments, ResolveInfo $resolveInfo): mixed
    {
        if (!isset($arguments['format'])) {
            $arguments['format'] = 'yyyy-MM-dd HH:mm:ss t';
        }
        if (!isset($arguments['locale'])) {
            $arguments['locale'] = 'en_US';
        }
        if (!isset($arguments['timezone'])) {
            $arguments['timezone'] = Craft::$app->getTimeZone();
        }
        $calendar = isset($arguments['calendar']) ? $arguments['calendar'] : self::DEFAULT_CALENDAR;
        $value = new DateTime($value);
        $date = DateTimeHelper::intlDate($value, $calendar, $arguments['format'], $arguments['locale'], $arguments['timezone']);
        return $date;
    }
}
