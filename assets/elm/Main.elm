module Main exposing (main)

{-| Comment Moderation admin UI.

The Elm app renders into the `#pccm-admin-root` mount node emitted by the
admin page template, talks back to WordPress through the six admin-ajax
endpoints exposed by `Rule_Ajax_Controller`, and renders the five Stitch
screens for the rebuild (project 17078749349029816801): the list dashboard
and the four add/edit forms (regex / wildcard / IP range / advanced
composite). CIDR is intentionally absent — the spec calls the CIDR Stitch
screen "intentionally unused".

The dark-mode design rules — Inter typeface, 8px rounding, monospace
expressions, colour-coded response badges (Pending recycle / Spam warning /
Trash trash) — live in `assets/css/admin.css`; this module is responsible
only for behaviour and applying the matching `pccm-*` class names so the
stylesheet can paint them.

-}

import Browser
import Dict exposing (Dict)
import Html exposing (..)
import Html.Attributes exposing (..)
import Html.Events exposing (..)
import Http
import Json.Decode as D exposing (Decoder)
import Json.Encode as E
import Url



-- FLAGS -----------------------------------------------------------------------


type alias Flags =
    { ajaxUrl : String
    , ajaxNonce : String
    , ajaxActions : AjaxActions
    , pageSlug : String
    }


type alias AjaxActions =
    { list : String
    , get : String
    , create : String
    , update : String
    , delete : String
    , clear : String
    }


flagsDecoder : Decoder Flags
flagsDecoder =
    D.map4 Flags
        (D.field "ajaxUrl" D.string)
        (D.field "ajaxNonce" D.string)
        (D.field "ajaxActions" actionsDecoder)
        (D.field "pageSlug" D.string)


actionsDecoder : Decoder AjaxActions
actionsDecoder =
    D.map6 AjaxActions
        (D.field "list" D.string)
        (D.field "get" D.string)
        (D.field "create" D.string)
        (D.field "update" D.string)
        (D.field "delete" D.string)
        (D.field "clear" D.string)



-- DOMAIN ----------------------------------------------------------------------


type RuleType
    = Regex
    | Wildcard
    | IpRange
    | Conditional


ruleTypeToString : RuleType -> String
ruleTypeToString t =
    case t of
        Regex ->
            "regex"

        Wildcard ->
            "wildcard"

        IpRange ->
            "ip_range"

        Conditional ->
            "conditional"


ruleTypeLabel : RuleType -> String
ruleTypeLabel t =
    case t of
        Regex ->
            "Regular Expression"

        Wildcard ->
            "Wildcard"

        IpRange ->
            "IP Range"

        Conditional ->
            "Conditional"


ruleTypeFromString : String -> Maybe RuleType
ruleTypeFromString s =
    case s of
        "regex" ->
            Just Regex

        "wildcard" ->
            Just Wildcard

        "ip_range" ->
            Just IpRange

        "conditional" ->
            Just Conditional

        _ ->
            Nothing


allRuleTypes : List RuleType
allRuleTypes =
    [ Regex, Wildcard, IpRange, Conditional ]


type ResponseChoice
    = Pending
    | Spam
    | Trash
    | Approved


responseToString : ResponseChoice -> String
responseToString r =
    case r of
        Pending ->
            "pending"

        Spam ->
            "spam"

        Trash ->
            "trash"

        Approved ->
            "approved"


responseLabel : ResponseChoice -> String
responseLabel r =
    case r of
        Pending ->
            "Pending"

        Spam ->
            "Spam"

        Trash ->
            "Trash"

        Approved ->
            "Approved"


responseFromString : String -> Maybe ResponseChoice
responseFromString s =
    case s of
        "pending" ->
            Just Pending

        "spam" ->
            Just Spam

        "trash" ->
            Just Trash

        "approved" ->
            Just Approved

        _ ->
            Nothing


selectableResponses : List ResponseChoice
selectableResponses =
    [ Pending, Spam, Trash ]


responseBadgeClass : ResponseChoice -> String
responseBadgeClass r =
    case r of
        Pending ->
            "pccm-badge pccm-badge--pending"

        Spam ->
            "pccm-badge pccm-badge--spam"

        Trash ->
            "pccm-badge pccm-badge--trash"

        Approved ->
            "pccm-badge pccm-badge--approved"


responseIcon : ResponseChoice -> String
responseIcon r =
    case r of
        Pending ->
            "♻"

        Spam ->
            "⚠"

        Trash ->
            "🗑"

        Approved ->
            "✓"


type Part
    = PartName
    | PartEmail
    | PartUrl
    | PartIp
    | PartUserAgent
    | PartContent


partToString : Part -> String
partToString p =
    case p of
        PartName ->
            "name"

        PartEmail ->
            "email"

        PartUrl ->
            "url"

        PartIp ->
            "ip"

        PartUserAgent ->
            "user_agent"

        PartContent ->
            "content"


partLabel : Part -> String
partLabel p =
    case p of
        PartName ->
            "Name"

        PartEmail ->
            "Email"

        PartUrl ->
            "URL"

        PartIp ->
            "IP"

        PartUserAgent ->
            "User-Agent"

        PartContent ->
            "Content"


partFromString : String -> Maybe Part
partFromString s =
    case s of
        "name" ->
            Just PartName

        "email" ->
            Just PartEmail

        "url" ->
            Just PartUrl

        "ip" ->
            Just PartIp

        "user_agent" ->
            Just PartUserAgent

        "content" ->
            Just PartContent

        _ ->
            Nothing


allParts : List Part
allParts =
    [ PartName, PartEmail, PartUrl, PartIp, PartUserAgent, PartContent ]


type Operator
    = OpIs
    | OpIsNot
    | OpContains
    | OpDoesNotContain
    | OpStartsWith
    | OpEndsWith
    | OpIn
    | OpNotIn
    | OpMatches
    | OpDoesNotMatch
    | OpWildcard
    | OpNotWildcard


operatorToString : Operator -> String
operatorToString o =
    case o of
        OpIs ->
            "is"

        OpIsNot ->
            "is_not"

        OpContains ->
            "contains"

        OpDoesNotContain ->
            "does_not_contain"

        OpStartsWith ->
            "starts_with"

        OpEndsWith ->
            "ends_with"

        OpIn ->
            "in"

        OpNotIn ->
            "not_in"

        OpMatches ->
            "matches"

        OpDoesNotMatch ->
            "does_not_match"

        OpWildcard ->
            "wildcard"

        OpNotWildcard ->
            "not_wildcard"


operatorLabel : Operator -> String
operatorLabel o =
    case o of
        OpIs ->
            "is"

        OpIsNot ->
            "is not"

        OpContains ->
            "contains"

        OpDoesNotContain ->
            "does not contain"

        OpStartsWith ->
            "starts with"

        OpEndsWith ->
            "ends with"

        OpIn ->
            "in"

        OpNotIn ->
            "not in"

        OpMatches ->
            "matches"

        OpDoesNotMatch ->
            "does not match"

        OpWildcard ->
            "wildcard"

        OpNotWildcard ->
            "not wildcard"


operatorFromString : String -> Maybe Operator
operatorFromString s =
    case s of
        "is" ->
            Just OpIs

        "is_not" ->
            Just OpIsNot

        "contains" ->
            Just OpContains

        "does_not_contain" ->
            Just OpDoesNotContain

        "starts_with" ->
            Just OpStartsWith

        "ends_with" ->
            Just OpEndsWith

        "in" ->
            Just OpIn

        "not_in" ->
            Just OpNotIn

        "matches" ->
            Just OpMatches

        "does_not_match" ->
            Just OpDoesNotMatch

        "wildcard" ->
            Just OpWildcard

        "not_wildcard" ->
            Just OpNotWildcard

        _ ->
            Nothing


allOperators : List Operator
allOperators =
    [ OpIs
    , OpIsNot
    , OpContains
    , OpDoesNotContain
    , OpStartsWith
    , OpEndsWith
    , OpIn
    , OpNotIn
    , OpMatches
    , OpDoesNotMatch
    , OpWildcard
    , OpNotWildcard
    ]


type Combinator
    = And
    | Or


combinatorToString : Combinator -> String
combinatorToString c =
    case c of
        And ->
            "and"

        Or ->
            "or"


combinatorLabel : Combinator -> String
combinatorLabel c =
    case c of
        And ->
            "AND"

        Or ->
            "OR"


combinatorFromString : String -> Combinator
combinatorFromString s =
    case s of
        "or" ->
            Or

        _ ->
            And



-- TREE ------------------------------------------------------------------------


type Node
    = NGroup Combinator (List Node)
    | NCondition Part Operator String


emptyCondition : Node
emptyCondition =
    NCondition PartContent OpContains ""


emptyGroup : Node
emptyGroup =
    NGroup And [ emptyCondition ]



-- RULE ------------------------------------------------------------------------


type alias Rule =
    { id : Maybe Int
    , ruleType : RuleType
    , name : String
    , description : String
    , response : ResponseChoice
    , commentParts : List Part
    , pattern : String
    , startIp : String
    , endIp : String
    , root : Maybe Node
    , timesUsed : Int
    , lastUsed : Maybe String
    , lastUpdated : Maybe String
    }


emptyRule : RuleType -> Rule
emptyRule t =
    { id = Nothing
    , ruleType = t
    , name = ""
    , description = ""
    , response = Pending
    , commentParts =
        if t == Regex || t == Wildcard then
            [ PartContent ]

        else
            []
    , pattern = ""
    , startIp = ""
    , endIp = ""
    , root =
        if t == Conditional then
            Just emptyGroup

        else
            Nothing
    , timesUsed = 0
    , lastUsed = Nothing
    , lastUpdated = Nothing
    }



-- DECODERS --------------------------------------------------------------------


ruleDecoder : Decoder Rule
ruleDecoder =
    D.field "type" D.string
        |> D.andThen
            (\s ->
                case ruleTypeFromString s of
                    Just t ->
                        ruleDecoderForType t

                    Nothing ->
                        D.fail ("Unknown rule type: " ++ s)
            )


ruleDecoderForType : RuleType -> Decoder Rule
ruleDecoderForType rType =
    D.succeed
        (\id name desc resp parts patt sIp eIp root usage ->
            { id = id
            , ruleType = rType
            , name = Maybe.withDefault "" name
            , description = Maybe.withDefault "" desc
            , response = resp
            , commentParts = parts
            , pattern = patt
            , startIp = sIp
            , endIp = eIp
            , root = root
            , timesUsed = usage.timesUsed
            , lastUsed = usage.lastUsed
            , lastUpdated = usage.lastUpdated
            }
        )
        |> andMap (D.field "id" (D.nullable D.int))
        |> andMap (D.oneOf [ D.field "name" (D.nullable D.string), D.succeed Nothing ])
        |> andMap (D.oneOf [ D.field "description" (D.nullable D.string), D.succeed Nothing ])
        |> andMap responseDecoder
        |> andMap commentPartsDecoder
        |> andMap (D.oneOf [ D.field "pattern" D.string, D.succeed "" ])
        |> andMap (D.oneOf [ D.field "start_ip" D.string, D.succeed "" ])
        |> andMap (D.oneOf [ D.field "end_ip" D.string, D.succeed "" ])
        |> andMap (D.oneOf [ D.field "root" (D.map Just nodeDecoder), D.succeed Nothing ])
        |> andMap usageDecoder


andMap : Decoder a -> Decoder (a -> b) -> Decoder b
andMap =
    D.map2 (|>)


responseDecoder : Decoder ResponseChoice
responseDecoder =
    D.field "response" D.string
        |> D.andThen
            (\s ->
                case responseFromString s of
                    Just r ->
                        D.succeed r

                    Nothing ->
                        D.fail ("Unknown response: " ++ s)
            )


commentPartsDecoder : Decoder (List Part)
commentPartsDecoder =
    D.oneOf
        [ D.field "comment_parts" (D.list D.string)
        , D.succeed []
        ]
        |> D.map (List.filterMap partFromString)


type alias Usage =
    { timesUsed : Int
    , lastUsed : Maybe String
    , lastUpdated : Maybe String
    }


usageDecoder : Decoder Usage
usageDecoder =
    D.oneOf
        [ D.field "usage_stats"
            (D.map3 Usage
                (D.field "times_used" D.int)
                (D.field "last_used" (D.nullable D.string))
                (D.field "last_updated" (D.nullable D.string))
            )
        , D.succeed (Usage 0 Nothing Nothing)
        ]


nodeDecoder : Decoder Node
nodeDecoder =
    D.oneOf
        [ D.field "combinator" D.string
            |> D.andThen
                (\c ->
                    D.field "children" (D.list (D.lazy (\_ -> nodeDecoder)))
                        |> D.map (\kids -> NGroup (combinatorFromString c) kids)
                )
        , D.map3 NCondition
            (D.field "part" D.string
                |> D.andThen
                    (\s ->
                        case partFromString s of
                            Just p ->
                                D.succeed p

                            Nothing ->
                                D.succeed PartContent
                    )
            )
            (D.field "operator" D.string
                |> D.andThen
                    (\s ->
                        case operatorFromString s of
                            Just o ->
                                D.succeed o

                            Nothing ->
                                D.succeed OpContains
                    )
            )
            (D.field "value" D.string)
        ]



-- ENCODERS --------------------------------------------------------------------


encodeRule : Rule -> List ( String, String )
encodeRule r =
    let
        common =
            [ ( "type", ruleTypeToString r.ruleType )
            , ( "name", r.name )
            , ( "description", r.description )
            , ( "response", responseToString r.response )
            ]

        typeSpecific =
            case r.ruleType of
                Regex ->
                    [ ( "pattern", r.pattern ) ]
                        ++ List.map (\p -> ( "comment_parts[]", partToString p )) r.commentParts

                Wildcard ->
                    [ ( "pattern", r.pattern ) ]
                        ++ List.map (\p -> ( "comment_parts[]", partToString p )) r.commentParts

                IpRange ->
                    [ ( "start_ip", r.startIp )
                    , ( "end_ip", r.endIp )
                    ]

                Conditional ->
                    case r.root of
                        Just node ->
                            [ ( "root", E.encode 0 (encodeNode node) ) ]

                        Nothing ->
                            [ ( "root", E.encode 0 (encodeNode emptyGroup) ) ]
    in
    common ++ typeSpecific


encodeNode : Node -> E.Value
encodeNode n =
    case n of
        NGroup c children ->
            E.object
                [ ( "combinator", E.string (combinatorToString c) )
                , ( "children", E.list encodeNode children )
                ]

        NCondition p o v ->
            E.object
                [ ( "part", E.string (partToString p) )
                , ( "operator", E.string (operatorToString o) )
                , ( "value", E.string v )
                ]



-- MODEL -----------------------------------------------------------------------


type alias Model =
    { flags : Flags
    , bootstrapError : Maybe String
    , busy : Bool
    , banner : Banner
    , rules : List Rule
    , total : Int
    , visible : Int
    , page : Int
    , hasMore : Bool
    , filtersOpen : Bool
    , filterDraft : FilterState
    , filterApplied : FilterState
    , detailsOpen : Dict Int Bool
    , editor : Editor
    , typeToAdd : String
    }


type Banner
    = NoBanner
    | SuccessBanner String
    | ErrorBanner String


type alias FilterState =
    { search : String
    , types : List RuleType
    , parts : List Part
    , responses : List ResponseChoice
    }


emptyFilter : FilterState
emptyFilter =
    { search = "", types = [], parts = [], responses = [] }


type Editor
    = EditorClosed
    | EditorOpen Rule



-- MSG -------------------------------------------------------------------------


type Msg
    = GotList (Result Http.Error ListResponse)
    | GotSaveResult (Result Http.Error SaveResponse)
    | GotDeleteResult (Result Http.Error MessageResponse)
    | GotClearResult (Result Http.Error MessageResponse)
    | TypeToAddChanged String
    | AddRuleClicked
    | ClearAllRulesClicked
    | EditRuleClicked Rule
    | DeleteRuleClicked Int
    | EditorCancelled
    | EditorSaveClicked
    | FieldChanged FieldUpdate
    | ToggleDetailsClicked Int
    | ToggleFiltersClicked
    | FilterSearchChanged String
    | ToggleFilterType RuleType
    | ToggleFilterPart Part
    | ToggleFilterResponse ResponseChoice
    | ApplyFiltersClicked
    | ClearFiltersClicked
    | ShowMoreClicked
    | TreeMutated TreeMutation


type FieldUpdate
    = SetName String
    | SetDescription String
    | SetResponse String
    | SetPattern String
    | SetStartIp String
    | SetEndIp String
    | ToggleEditorPart Part


type TreeMutation
    = TmToggleCombinator (List Int)
    | TmAddCondition (List Int)
    | TmAddGroup (List Int)
    | TmRemove (List Int)
    | TmSetPart (List Int) String
    | TmSetOperator (List Int) String
    | TmSetValue (List Int) String



-- AJAX RESPONSE TYPES ---------------------------------------------------------


type alias ListResponse =
    { rules : List Rule
    , total : Int
    , page : Int
    , pageSize : Int
    , visible : Int
    , hasMore : Bool
    }


listResponseDecoder : Decoder ListResponse
listResponseDecoder =
    D.field "data"
        (D.map6 ListResponse
            (D.field "rules" (D.list ruleDecoder))
            (D.field "total" D.int)
            (D.field "page" D.int)
            (D.field "page_size" D.int)
            (D.field "visible" D.int)
            (D.field "has_more" D.bool)
        )


type alias SaveResponse =
    { rule : Rule
    , message : String
    }


saveResponseDecoder : Decoder SaveResponse
saveResponseDecoder =
    D.field "data"
        (D.map2 SaveResponse
            (D.field "rule" ruleDecoder)
            (D.field "message" D.string)
        )


type alias MessageResponse =
    { message : String }


messageResponseDecoder : Decoder MessageResponse
messageResponseDecoder =
    D.field "data"
        (D.map MessageResponse
            (D.oneOf [ D.field "message" D.string, D.succeed "" ])
        )



-- INIT ------------------------------------------------------------------------


main : Program E.Value Model Msg
main =
    Browser.element
        { init = init
        , view = view
        , update = update
        , subscriptions = \_ -> Sub.none
        }


init : E.Value -> ( Model, Cmd Msg )
init raw =
    case D.decodeValue flagsDecoder raw of
        Ok flags ->
            ( initialModel flags Nothing
            , listRulesCmd flags emptyFilter 1
            )

        Err e ->
            ( initialModel emptyFlags (Just (D.errorToString e))
            , Cmd.none
            )


emptyFlags : Flags
emptyFlags =
    { ajaxUrl = ""
    , ajaxNonce = ""
    , ajaxActions =
        { list = ""
        , get = ""
        , create = ""
        , update = ""
        , delete = ""
        , clear = ""
        }
    , pageSlug = ""
    }


initialModel : Flags -> Maybe String -> Model
initialModel flags bootstrapError =
    { flags = flags
    , bootstrapError = bootstrapError
    , busy = bootstrapError == Nothing
    , banner = NoBanner
    , rules = []
    , total = 0
    , visible = 0
    , page = 1
    , hasMore = False
    , filtersOpen = False
    , filterDraft = emptyFilter
    , filterApplied = emptyFilter
    , detailsOpen = Dict.empty
    , editor = EditorClosed
    , typeToAdd = ""
    }



-- UPDATE ----------------------------------------------------------------------


update : Msg -> Model -> ( Model, Cmd Msg )
update msg model =
    case msg of
        GotList result ->
            case result of
                Ok payload ->
                    ( { model
                        | busy = False
                        , rules =
                            if payload.page == 1 then
                                payload.rules

                            else
                                model.rules ++ payload.rules
                        , total = payload.total
                        , visible = payload.visible
                        , page = payload.page
                        , hasMore = payload.hasMore
                      }
                    , Cmd.none
                    )

                Err err ->
                    ( { model | busy = False, banner = ErrorBanner (errorToBanner err) }
                    , Cmd.none
                    )

        GotSaveResult result ->
            case result of
                Ok payload ->
                    ( { model
                        | busy = False
                        , banner = SuccessBanner payload.message
                        , editor = EditorClosed
                        , page = 1
                      }
                    , listRulesCmd model.flags model.filterApplied 1
                    )

                Err err ->
                    ( { model | busy = False, banner = ErrorBanner (errorToBanner err) }
                    , Cmd.none
                    )

        GotDeleteResult result ->
            handleMessage model result

        GotClearResult result ->
            handleMessage model result

        TypeToAddChanged value ->
            ( { model | typeToAdd = value }, Cmd.none )

        AddRuleClicked ->
            case ruleTypeFromString model.typeToAdd of
                Just t ->
                    ( { model | editor = EditorOpen (emptyRule t), banner = NoBanner }
                    , Cmd.none
                    )

                Nothing ->
                    ( { model | banner = ErrorBanner "Please select a rule type." }, Cmd.none )

        ClearAllRulesClicked ->
            ( { model | busy = True, banner = NoBanner }
            , clearRulesCmd model.flags
            )

        EditRuleClicked rule ->
            ( { model | editor = EditorOpen rule, banner = NoBanner }, Cmd.none )

        DeleteRuleClicked id ->
            ( { model | busy = True, banner = NoBanner }
            , deleteRuleCmd model.flags id
            )

        EditorCancelled ->
            ( { model | editor = EditorClosed, banner = NoBanner }, Cmd.none )

        EditorSaveClicked ->
            case model.editor of
                EditorOpen rule ->
                    ( { model | busy = True, banner = NoBanner }
                    , saveRuleCmd model.flags rule
                    )

                EditorClosed ->
                    ( model, Cmd.none )

        FieldChanged update_ ->
            ( { model | editor = applyFieldUpdate update_ model.editor }, Cmd.none )

        ToggleDetailsClicked id ->
            let
                cur =
                    Dict.get id model.detailsOpen |> Maybe.withDefault False
            in
            ( { model | detailsOpen = Dict.insert id (not cur) model.detailsOpen }
            , Cmd.none
            )

        ToggleFiltersClicked ->
            ( { model | filtersOpen = not model.filtersOpen }, Cmd.none )

        FilterSearchChanged s ->
            ( { model | filterDraft = setSearch s model.filterDraft }, Cmd.none )

        ToggleFilterType t ->
            ( { model | filterDraft = toggleTypeFilter t model.filterDraft }, Cmd.none )

        ToggleFilterPart p ->
            ( { model | filterDraft = togglePartFilter p model.filterDraft }, Cmd.none )

        ToggleFilterResponse r ->
            ( { model | filterDraft = toggleResponseFilter r model.filterDraft }, Cmd.none )

        ApplyFiltersClicked ->
            ( { model | busy = True, filterApplied = model.filterDraft, page = 1 }
            , listRulesCmd model.flags model.filterDraft 1
            )

        ClearFiltersClicked ->
            ( { model
                | busy = True
                , filterDraft = emptyFilter
                , filterApplied = emptyFilter
                , page = 1
              }
            , listRulesCmd model.flags emptyFilter 1
            )

        ShowMoreClicked ->
            ( { model | busy = True }
            , listRulesCmd model.flags model.filterApplied (model.page + 1)
            )

        TreeMutated mutation ->
            ( { model | editor = applyTreeMutation mutation model.editor }, Cmd.none )


handleMessage : Model -> Result Http.Error MessageResponse -> ( Model, Cmd Msg )
handleMessage model result =
    case result of
        Ok payload ->
            ( { model
                | busy = False
                , banner = SuccessBanner payload.message
                , page = 1
              }
            , listRulesCmd model.flags model.filterApplied 1
            )

        Err err ->
            ( { model | busy = False, banner = ErrorBanner (errorToBanner err) }
            , Cmd.none
            )


setSearch : String -> FilterState -> FilterState
setSearch s f =
    { f | search = s }


toggleTypeFilter : RuleType -> FilterState -> FilterState
toggleTypeFilter t f =
    { f | types = toggleList t f.types }


togglePartFilter : Part -> FilterState -> FilterState
togglePartFilter p f =
    { f | parts = toggleList p f.parts }


toggleResponseFilter : ResponseChoice -> FilterState -> FilterState
toggleResponseFilter r f =
    { f | responses = toggleList r f.responses }


toggleList : a -> List a -> List a
toggleList x xs =
    if List.member x xs then
        List.filter (\y -> y /= x) xs

    else
        xs ++ [ x ]


applyFieldUpdate : FieldUpdate -> Editor -> Editor
applyFieldUpdate u editor =
    case editor of
        EditorClosed ->
            editor

        EditorOpen rule ->
            EditorOpen (applyToRule u rule)


applyToRule : FieldUpdate -> Rule -> Rule
applyToRule u rule =
    case u of
        SetName s ->
            { rule | name = s }

        SetDescription s ->
            { rule | description = s }

        SetResponse s ->
            case responseFromString s of
                Just r ->
                    { rule | response = r }

                Nothing ->
                    rule

        SetPattern s ->
            { rule | pattern = s }

        SetStartIp s ->
            { rule | startIp = s }

        SetEndIp s ->
            { rule | endIp = s }

        ToggleEditorPart p ->
            { rule | commentParts = toggleList p rule.commentParts }


applyTreeMutation : TreeMutation -> Editor -> Editor
applyTreeMutation mutation editor =
    case editor of
        EditorClosed ->
            editor

        EditorOpen rule ->
            case rule.root of
                Just root ->
                    EditorOpen { rule | root = Just (applyMutation mutation root) }

                Nothing ->
                    editor


applyMutation : TreeMutation -> Node -> Node
applyMutation mutation root =
    case mutation of
        TmToggleCombinator path ->
            transformAt path toggleCombinator root

        TmAddCondition path ->
            transformAt path (appendChild emptyCondition) root

        TmAddGroup path ->
            transformAt path (appendChild emptyGroup) root

        TmRemove path ->
            removeAt path root

        TmSetPart path s ->
            case partFromString s of
                Just p ->
                    transformAt path (setConditionPart p) root

                Nothing ->
                    root

        TmSetOperator path s ->
            case operatorFromString s of
                Just o ->
                    transformAt path (setConditionOperator o) root

                Nothing ->
                    root

        TmSetValue path s ->
            transformAt path (setConditionValue s) root


toggleCombinator : Node -> Node
toggleCombinator n =
    case n of
        NGroup And kids ->
            NGroup Or kids

        NGroup Or kids ->
            NGroup And kids

        other ->
            other


appendChild : Node -> Node -> Node
appendChild new n =
    case n of
        NGroup c kids ->
            NGroup c (kids ++ [ new ])

        other ->
            other


setConditionPart : Part -> Node -> Node
setConditionPart p n =
    case n of
        NCondition _ o v ->
            NCondition p o v

        other ->
            other


setConditionOperator : Operator -> Node -> Node
setConditionOperator o n =
    case n of
        NCondition p _ v ->
            NCondition p o v

        other ->
            other


setConditionValue : String -> Node -> Node
setConditionValue s n =
    case n of
        NCondition p o _ ->
            NCondition p o s

        other ->
            other


transformAt : List Int -> (Node -> Node) -> Node -> Node
transformAt path fn root =
    case path of
        [] ->
            fn root

        idx :: rest ->
            case root of
                NGroup c kids ->
                    NGroup c
                        (List.indexedMap
                            (\i child ->
                                if i == idx then
                                    transformAt rest fn child

                                else
                                    child
                            )
                            kids
                        )

                NCondition _ _ _ ->
                    root


removeAt : List Int -> Node -> Node
removeAt path root =
    case List.reverse path of
        [] ->
            root

        last :: revRest ->
            let
                parentPath =
                    List.reverse revRest
            in
            transformAt parentPath
                (\n ->
                    case n of
                        NGroup c kids ->
                            let
                                filtered =
                                    List.indexedMap (\i k -> ( i, k )) kids
                                        |> List.filter (\( i, _ ) -> i /= last)
                                        |> List.map Tuple.second
                            in
                            if List.isEmpty filtered then
                                NGroup c [ emptyCondition ]

                            else
                                NGroup c filtered

                        other ->
                            other
                )
                root


errorToBanner : Http.Error -> String
errorToBanner err =
    case err of
        Http.BadStatus _ ->
            "The server rejected the request."

        Http.NetworkError ->
            "Network error — please try again."

        Http.Timeout ->
            "Request timed out — please try again."

        Http.BadUrl _ ->
            "Internal error — bad URL."

        Http.BadBody msg ->
            "Unexpected response: " ++ msg



-- HTTP ------------------------------------------------------------------------


buildFormBody : List ( String, String ) -> Http.Body
buildFormBody pairs =
    pairs
        |> List.map (\( k, v ) -> percentEncode k ++ "=" ++ percentEncode v)
        |> String.join "&"
        |> Http.stringBody "application/x-www-form-urlencoded"


percentEncode : String -> String
percentEncode s =
    -- The browser exposes encodeURIComponent indirectly via Elm's Url.percentEncode.
    -- elm/url 1.0.0 provides the function as `Url.percentEncode`.
    Url.percentEncode s


listRulesCmd : Flags -> FilterState -> Int -> Cmd Msg
listRulesCmd flags filter page =
    let
        base =
            [ ( "action", flags.ajaxActions.list )
            , ( "_wpnonce", flags.ajaxNonce )
            , ( "page", String.fromInt page )
            , ( "search", filter.search )
            ]

        types =
            List.map (\t -> ( "types[]", ruleTypeToString t )) filter.types

        parts =
            List.map (\p -> ( "parts[]", partToString p )) filter.parts

        responses =
            List.map (\r -> ( "responses[]", responseToString r )) filter.responses
    in
    Http.post
        { url = flags.ajaxUrl
        , body = buildFormBody (base ++ types ++ parts ++ responses)
        , expect = Http.expectJson GotList listResponseDecoder
        }


saveRuleCmd : Flags -> Rule -> Cmd Msg
saveRuleCmd flags rule =
    let
        ( action, idPart ) =
            case rule.id of
                Just id ->
                    ( flags.ajaxActions.update, [ ( "id", String.fromInt id ) ] )

                Nothing ->
                    ( flags.ajaxActions.create, [] )

        body =
            [ ( "action", action ), ( "_wpnonce", flags.ajaxNonce ) ]
                ++ idPart
                ++ encodeRule rule
    in
    Http.post
        { url = flags.ajaxUrl
        , body = buildFormBody body
        , expect = Http.expectJson GotSaveResult saveResponseDecoder
        }


deleteRuleCmd : Flags -> Int -> Cmd Msg
deleteRuleCmd flags id =
    Http.post
        { url = flags.ajaxUrl
        , body =
            buildFormBody
                [ ( "action", flags.ajaxActions.delete )
                , ( "_wpnonce", flags.ajaxNonce )
                , ( "id", String.fromInt id )
                ]
        , expect = Http.expectJson GotDeleteResult messageResponseDecoder
        }


clearRulesCmd : Flags -> Cmd Msg
clearRulesCmd flags =
    Http.post
        { url = flags.ajaxUrl
        , body =
            buildFormBody
                [ ( "action", flags.ajaxActions.clear )
                , ( "_wpnonce", flags.ajaxNonce )
                ]
        , expect = Http.expectJson GotClearResult messageResponseDecoder
        }



-- VIEW ------------------------------------------------------------------------


view : Model -> Html Msg
view model =
    div
        [ id "pccm-admin-root"
        , class "pccm-app"
        , attribute "data-mode" "dark"
        ]
        [ viewBootstrapError model
        , viewBanner model
        , viewEditor model
        , viewRulesCard model
        , viewMetaRow model
        , viewFiltersPanel model
        , viewRulesList model
        , viewShowMore model
        ]


viewBootstrapError : Model -> Html Msg
viewBootstrapError model =
    case model.bootstrapError of
        Just msg ->
            div [ class "pccm-banner pccm-banner--error" ]
                [ text ("Comment Moderation failed to start: " ++ msg) ]

        Nothing ->
            text ""


viewBanner : Model -> Html Msg
viewBanner model =
    case model.banner of
        NoBanner ->
            text ""

        SuccessBanner msg ->
            div
                [ class "pccm-banner pccm-banner--success"
                , attribute "role" "status"
                ]
                [ text msg ]

        ErrorBanner msg ->
            div
                [ class "pccm-banner pccm-banner--error"
                , attribute "role" "alert"
                ]
                [ text msg ]


viewEditor : Model -> Html Msg
viewEditor model =
    case model.editor of
        EditorClosed ->
            text ""

        EditorOpen rule ->
            let
                heading =
                    (if rule.id == Nothing then
                        "Add "

                     else
                        "Edit "
                    )
                        ++ ruleTypeLabel rule.ruleType
                        ++ " Rule"
            in
            section
                [ class "pccm-editor"
                , attribute "aria-label" heading
                ]
                [ h2 [ class "pccm-editor__title" ] [ text heading ]
                , viewEditorForm rule
                , viewEditorFooter rule model.busy
                ]


viewEditorForm : Rule -> Html Msg
viewEditorForm rule =
    div []
        [ viewNameAndDescription rule
        , viewResponseField rule
        , viewTypeSpecificFields rule
        ]


viewNameAndDescription : Rule -> Html Msg
viewNameAndDescription rule =
    div [ class "pccm-grid-2" ]
        [ div [ class "pccm-field" ]
            [ label [ class "pccm-field__label", for "pccm-rule-name" ]
                [ text "Rule Name (optional)" ]
            , input
                [ id "pccm-rule-name"
                , type_ "text"
                , class "pccm-input"
                , value rule.name
                , onInput (FieldChanged << SetName)
                ]
                []
            ]
        , div [ class "pccm-field" ]
            [ label [ class "pccm-field__label", for "pccm-rule-desc" ]
                [ text "Rule Description (optional)" ]
            , textarea
                [ id "pccm-rule-desc"
                , class "pccm-textarea"
                , value rule.description
                , onInput (FieldChanged << SetDescription)
                ]
                []
            ]
        ]


viewResponseField : Rule -> Html Msg
viewResponseField rule =
    fieldset [ class "pccm-fieldset" ]
        (legend [ class "pccm-fieldset__legend" ] [ text "Response" ]
            :: List.map (viewResponseRadio rule) selectableResponses
        )


viewResponseRadio : Rule -> ResponseChoice -> Html Msg
viewResponseRadio rule choice =
    let
        idValue =
            "pccm-response-" ++ responseToString choice
    in
    label [ class "pccm-check", for idValue ]
        [ input
            [ id idValue
            , type_ "radio"
            , name "pccm-response"
            , value (responseToString choice)
            , checked (rule.response == choice)
            , onInput (FieldChanged << SetResponse)
            ]
            []
        , span [ class (responseBadgeClass choice) ]
            [ span
                [ class "pccm-badge__icon"
                , attribute "aria-hidden" "true"
                ]
                [ text (responseIcon choice) ]
            , text (responseLabel choice)
            ]
        ]


viewTypeSpecificFields : Rule -> Html Msg
viewTypeSpecificFields rule =
    case rule.ruleType of
        Regex ->
            viewPatternFields rule "Regular expression (with delimiters)"

        Wildcard ->
            viewPatternFields rule "Wildcard pattern (* and ?)"

        IpRange ->
            viewIpRangeFields rule

        Conditional ->
            viewConditionalBuilder rule


viewPatternFields : Rule -> String -> Html Msg
viewPatternFields rule lab =
    div []
        [ div [ class "pccm-field" ]
            [ label [ class "pccm-field__label", for "pccm-rule-pattern" ]
                [ text lab ]
            , input
                [ id "pccm-rule-pattern"
                , type_ "text"
                , class "pccm-input pccm-input--mono"
                , value rule.pattern
                , onInput (FieldChanged << SetPattern)
                ]
                []
            ]
        , fieldset [ class "pccm-fieldset" ]
            (legend [ class "pccm-fieldset__legend" ] [ text "Comment parts to scan" ]
                :: List.map (viewPartCheckbox rule) allParts
            )
        ]


viewPartCheckbox : Rule -> Part -> Html Msg
viewPartCheckbox rule p =
    let
        idValue =
            "pccm-part-" ++ partToString p
    in
    label [ class "pccm-check", for idValue ]
        [ input
            [ id idValue
            , type_ "checkbox"
            , checked (List.member p rule.commentParts)
            , onClick (FieldChanged (ToggleEditorPart p))
            ]
            []
        , text (partLabel p)
        ]


viewIpRangeFields : Rule -> Html Msg
viewIpRangeFields rule =
    div [ class "pccm-grid-2" ]
        [ div [ class "pccm-field" ]
            [ label [ class "pccm-field__label", for "pccm-start-ip" ]
                [ text "Start IP" ]
            , input
                [ id "pccm-start-ip"
                , type_ "text"
                , class "pccm-input pccm-input--mono"
                , value rule.startIp
                , placeholder "203.0.113.10"
                , onInput (FieldChanged << SetStartIp)
                ]
                []
            ]
        , div [ class "pccm-field" ]
            [ label [ class "pccm-field__label", for "pccm-end-ip" ]
                [ text "End IP" ]
            , input
                [ id "pccm-end-ip"
                , type_ "text"
                , class "pccm-input pccm-input--mono"
                , value rule.endIp
                , placeholder "203.0.113.40"
                , onInput (FieldChanged << SetEndIp)
                ]
                []
            ]
        ]


viewEditorFooter : Rule -> Bool -> Html Msg
viewEditorFooter rule busy =
    div [ class "pccm-editor__footer" ]
        [ button
            [ type_ "button"
            , class "pccm-btn"
            , onClick EditorCancelled
            ]
            [ text "Cancel" ]
        , button
            [ type_ "button"
            , class "pccm-btn pccm-btn--primary"
            , onClick EditorSaveClicked
            , disabled busy
            ]
            [ if busy then
                span [ class "pccm-spinner", attribute "aria-hidden" "true" ] []

              else
                text ""
            , text
                (if rule.id == Nothing then
                    "Save Rule"

                 else
                    "Update Rule"
                )
            ]
        ]



-- CONDITIONAL BUILDER ---------------------------------------------------------


viewConditionalBuilder : Rule -> Html Msg
viewConditionalBuilder rule =
    case rule.root of
        Just node ->
            div [ class "pccm-field" ]
                [ label [ class "pccm-field__label" ] [ text "Conditions" ]
                , viewNode [] node
                ]

        Nothing ->
            text ""


viewNode : List Int -> Node -> Html Msg
viewNode path node =
    case node of
        NGroup combinator children ->
            viewGroup path combinator children

        NCondition p o v ->
            viewConditionRow path p o v


viewGroup : List Int -> Combinator -> List Node -> Html Msg
viewGroup path combinator children =
    let
        isRoot =
            List.isEmpty path
    in
    div [ class "pccm-builder-group" ]
        [ div [ class "pccm-builder-group__header" ]
            [ button
                [ type_ "button"
                , class "pccm-btn pccm-btn--ghost"
                , onClick (TreeMutated (TmToggleCombinator path))
                , attribute "aria-label" "Toggle combinator"
                ]
                [ span [ class "pccm-builder-group__combinator" ]
                    [ text (combinatorLabel combinator) ]
                ]
            , if isRoot then
                text ""

              else
                button
                    [ type_ "button"
                    , class "pccm-btn pccm-btn--danger"
                    , onClick (TreeMutated (TmRemove path))
                    ]
                    [ text "Remove Group" ]
            ]
        , div []
            (List.indexedMap (\i c -> viewNode (path ++ [ i ]) c) children)
        , div [ class "pccm-builder-actions" ]
            [ button
                [ type_ "button"
                , class "pccm-btn"
                , onClick (TreeMutated (TmAddCondition path))
                ]
                [ text "+ Condition" ]
            , button
                [ type_ "button"
                , class "pccm-btn"
                , onClick (TreeMutated (TmAddGroup path))
                ]
                [ text "+ Group" ]
            ]
        ]


viewConditionRow : List Int -> Part -> Operator -> String -> Html Msg
viewConditionRow path p o v =
    div [ class "pccm-builder-condition" ]
        [ select
            [ class "pccm-select"
            , attribute "aria-label" "Comment part"
            , onInput (\s -> TreeMutated (TmSetPart path s))
            ]
            (List.map (partOption p) allParts)
        , select
            [ class "pccm-select"
            , attribute "aria-label" "Operator"
            , onInput (\s -> TreeMutated (TmSetOperator path s))
            ]
            (List.map (operatorOption o) allOperators)
        , input
            [ class "pccm-input pccm-input--mono"
            , type_ "text"
            , value v
            , onInput (\s -> TreeMutated (TmSetValue path s))
            , attribute "aria-label" "Condition value"
            ]
            []
        , button
            [ type_ "button"
            , class "pccm-btn pccm-btn--danger"
            , onClick (TreeMutated (TmRemove path))
            , attribute "aria-label" "Remove condition"
            ]
            [ text "Remove" ]
        ]


partOption : Part -> Part -> Html Msg
partOption current p =
    option
        [ value (partToString p), selected (p == current) ]
        [ text (partLabel p) ]


operatorOption : Operator -> Operator -> Html Msg
operatorOption current o =
    option
        [ value (operatorToString o), selected (o == current) ]
        [ text (operatorLabel o) ]



-- RULES CARD + LIST -----------------------------------------------------------


viewRulesCard : Model -> Html Msg
viewRulesCard model =
    section [ class "pccm-card", attribute "aria-label" "Rules" ]
        [ div [ class "pccm-card__header" ]
            [ h2 [ class "pccm-card__title" ] [ text "Rules" ]
            , div [ class "pccm-card__actions" ]
                [ label [ class "pccm-sr-only", for "pccm-type-to-add" ]
                    [ text "Select rule type to add" ]
                , select
                    [ id "pccm-type-to-add"
                    , class "pccm-select"
                    , value model.typeToAdd
                    , onInput TypeToAddChanged
                    ]
                    (option [ value "" ] [ text "Select Rule Type" ]
                        :: List.map typeAddOption allRuleTypes
                    )
                , button
                    [ type_ "button"
                    , class "pccm-btn pccm-btn--primary"
                    , onClick AddRuleClicked
                    ]
                    [ text "Add Rule" ]
                , button
                    [ type_ "button"
                    , class "pccm-btn pccm-btn--danger"
                    , attribute "onclick"
                        "if(!confirm('Are you sure you want to Clear All Rules?')){event.stopImmediatePropagation();return false;}"
                    , onClick ClearAllRulesClicked
                    ]
                    [ text "Clear All Rules" ]
                ]
            ]
        ]


typeAddOption : RuleType -> Html Msg
typeAddOption t =
    option [ value (ruleTypeToString t) ] [ text (ruleTypeLabel t) ]


viewMetaRow : Model -> Html Msg
viewMetaRow model =
    div [ class "pccm-meta-row" ]
        [ span [ class "pccm-meta-row__count" ]
            [ text
                ("Showing "
                    ++ String.fromInt model.visible
                    ++ " of "
                    ++ String.fromInt model.total
                    ++ " rules"
                )
            ]
        , button
            [ type_ "button"
            , class "pccm-btn pccm-btn--ghost"
            , onClick ToggleFiltersClicked
            ]
            [ text
                (if model.filtersOpen then
                    "Hide Filters"

                 else
                    "Show Filters"
                )
            ]
        ]


viewFiltersPanel : Model -> Html Msg
viewFiltersPanel model =
    if model.filtersOpen then
        div [ class "pccm-filters" ]
            [ div [ class "pccm-field" ]
                [ label [ class "pccm-field__label", for "pccm-filter-search" ]
                    [ text "Search (pattern + name)" ]
                , input
                    [ id "pccm-filter-search"
                    , type_ "search"
                    , class "pccm-input"
                    , value model.filterDraft.search
                    , onInput FilterSearchChanged
                    ]
                    []
                ]
            , fieldset [ class "pccm-fieldset" ]
                (legend [ class "pccm-fieldset__legend" ] [ text "Rule types" ]
                    :: List.map (filterTypeCheckbox model.filterDraft.types) allRuleTypes
                )
            , fieldset [ class "pccm-fieldset" ]
                (legend [ class "pccm-fieldset__legend" ] [ text "Comment parts" ]
                    :: List.map (filterPartCheckbox model.filterDraft.parts) allParts
                )
            , fieldset [ class "pccm-fieldset" ]
                (legend [ class "pccm-fieldset__legend" ] [ text "Response" ]
                    :: List.map (filterResponseCheckbox model.filterDraft.responses) selectableResponses
                )
            , div [ class "pccm-filters__actions" ]
                [ button
                    [ type_ "button"
                    , class "pccm-btn"
                    , onClick ClearFiltersClicked
                    ]
                    [ text "Clear Filters" ]
                , button
                    [ type_ "button"
                    , class "pccm-btn pccm-btn--primary"
                    , onClick ApplyFiltersClicked
                    ]
                    [ text "Apply Filters" ]
                ]
            ]

    else
        text ""


filterTypeCheckbox : List RuleType -> RuleType -> Html Msg
filterTypeCheckbox selected_ t =
    label [ class "pccm-check" ]
        [ input
            [ type_ "checkbox"
            , checked (List.member t selected_)
            , onClick (ToggleFilterType t)
            ]
            []
        , text (ruleTypeLabel t)
        ]


filterPartCheckbox : List Part -> Part -> Html Msg
filterPartCheckbox selected_ p =
    label [ class "pccm-check" ]
        [ input
            [ type_ "checkbox"
            , checked (List.member p selected_)
            , onClick (ToggleFilterPart p)
            ]
            []
        , text (partLabel p)
        ]


filterResponseCheckbox : List ResponseChoice -> ResponseChoice -> Html Msg
filterResponseCheckbox selected_ r =
    label [ class "pccm-check" ]
        [ input
            [ type_ "checkbox"
            , checked (List.member r selected_)
            , onClick (ToggleFilterResponse r)
            ]
            []
        , text (responseLabel r)
        ]


viewRulesList : Model -> Html Msg
viewRulesList model =
    if List.isEmpty model.rules then
        div [ class "pccm-empty" ]
            [ if model.busy then
                span [ class "pccm-spinner", attribute "aria-hidden" "true" ] []

              else
                text ""
            , p []
                [ text
                    (if model.busy then
                        "Loading rules…"

                     else
                        "No rules yet — add one above to start moderating."
                    )
                ]
            ]

    else
        div [ class "pccm-rule-list" ]
            (List.map (viewRuleBlock model.detailsOpen) model.rules)


viewRuleBlock : Dict Int Bool -> Rule -> Html Msg
viewRuleBlock detailsOpen rule =
    let
        ruleId =
            Maybe.withDefault 0 rule.id

        isOpen =
            Dict.get ruleId detailsOpen |> Maybe.withDefault False
    in
    article
        [ class "pccm-rule"
        , attribute "data-rule-id" (String.fromInt ruleId)
        ]
        [ header [ class "pccm-rule__header" ]
            [ div []
                [ h3 [ class "pccm-rule__title" ]
                    [ text (Maybe.withDefault (ruleTypeLabel rule.ruleType) (nonEmpty rule.name))
                    , span [ class "pccm-rule__type" ]
                        [ text (ruleTypeLabel rule.ruleType) ]
                    ]
                , button
                    [ type_ "button"
                    , class "pccm-link-toggle"
                    , onClick (ToggleDetailsClicked ruleId)
                    , attribute "aria-expanded"
                        (if isOpen then
                            "true"

                         else
                            "false"
                        )
                    ]
                    [ text
                        (if isOpen then
                            "Hide rule details"

                         else
                            "Show rule details"
                        )
                    ]
                ]
            , div [ class "pccm-rule__actions" ]
                [ viewBadge rule.response
                , button
                    [ type_ "button"
                    , class "pccm-btn"
                    , onClick (EditRuleClicked rule)
                    ]
                    [ text "Edit" ]
                , button
                    [ type_ "button"
                    , class "pccm-btn pccm-btn--danger"
                    , onClick (DeleteRuleClicked ruleId)
                    ]
                    [ text "Delete" ]
                ]
            ]
        , if isOpen then
            viewRuleDetails rule

          else
            text ""
        , viewRuleSummary rule
        , viewRulePartsRow rule
        ]


viewBadge : ResponseChoice -> Html Msg
viewBadge r =
    span [ class (responseBadgeClass r), attribute "data-response" (responseToString r) ]
        [ span
            [ class "pccm-badge__icon"
            , attribute "aria-hidden" "true"
            ]
            [ text (responseIcon r) ]
        , text (responseLabel r)
        ]


viewRuleDetails : Rule -> Html Msg
viewRuleDetails rule =
    div [ class "pccm-rule__details" ]
        [ if String.isEmpty rule.description then
            text ""

          else
            p [ class "pccm-rule__details-row" ] [ text rule.description ]
        , p [ class "pccm-rule__details-row" ]
            [ text
                ("Used "
                    ++ String.fromInt rule.timesUsed
                    ++ " times"
                    ++ (case rule.lastUsed of
                            Just t ->
                                " | Last used " ++ t

                            Nothing ->
                                ""
                       )
                    ++ (case rule.lastUpdated of
                            Just t ->
                                " | Last updated " ++ t

                            Nothing ->
                                ""
                       )
                )
            ]
        ]


viewRuleSummary : Rule -> Html Msg
viewRuleSummary rule =
    case rule.ruleType of
        Regex ->
            div [ class "pccm-rule__summary" ] [ text rule.pattern ]

        Wildcard ->
            div [ class "pccm-rule__summary" ] [ text rule.pattern ]

        IpRange ->
            div [ class "pccm-rule__summary" ]
                [ text ("IP Range Between " ++ rule.startIp ++ " and " ++ rule.endIp) ]

        Conditional ->
            div [ class "pccm-rule__summary" ]
                [ text "All conditions must match"
                , case rule.root of
                    Just node ->
                        viewConditionSummary node

                    Nothing ->
                        text ""
                ]


viewConditionSummary : Node -> Html Msg
viewConditionSummary node =
    case node of
        NGroup combinator children ->
            ul [ class "pccm-rule__summary-list" ]
                [ li []
                    [ text (combinatorLabel combinator ++ ":") ]
                , li []
                    [ ul []
                        (List.map (\c -> li [] [ viewConditionSummary c ]) children)
                    ]
                ]

        NCondition p o v ->
            text (partLabel p ++ " " ++ operatorLabel o ++ " " ++ v)


viewRulePartsRow : Rule -> Html Msg
viewRulePartsRow rule =
    case rule.ruleType of
        IpRange ->
            text ""

        Conditional ->
            text ""

        _ ->
            div [ class "pccm-rule__parts" ]
                (List.map (viewRulePartChip rule.commentParts) allParts)


viewRulePartChip : List Part -> Part -> Html Msg
viewRulePartChip parts p =
    let
        on =
            List.member p parts
    in
    span
        [ class
            ("pccm-part-chip"
                ++ (if on then
                        " pccm-part-chip--on"

                    else
                        ""
                   )
            )
        ]
        [ text (partLabel p) ]


viewShowMore : Model -> Html Msg
viewShowMore model =
    if model.hasMore then
        div [ class "pccm-show-more" ]
            [ button
                [ type_ "button"
                , class "pccm-btn"
                , onClick ShowMoreClicked
                , disabled model.busy
                ]
                [ text "Show More Rules" ]
            ]

    else
        text ""


nonEmpty : String -> Maybe String
nonEmpty s =
    if String.isEmpty s then
        Nothing

    else
        Just s
