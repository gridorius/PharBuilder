<?php

namespace PharBuilder;

class Constants
{
    const NAMESPACE_REGEX = "/((namespace\s(?<namespace>[\w1-9_\\\\]+?))((;(?<content_t1>.+?))|(\{(?<content_t2>((?>[^{}]+)|(?7))*)\})))(?=\s*(?1)|\z)/s";
    const ENTITY_REGEX = "/(class|interface|trait)\s+(?<name>[\w1-9_\\\\]+)/";
}