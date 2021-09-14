<?php

require_once '../vendor/autoload.php';

$jaeger = AresTrace\JaegerInject::getInstance();

//顶级span【top】
$jaeger->init("top");
$jaeger->setTag("top", "k1", "v1");
$jaeger->setTag("top", "k2", "v2");
$jaeger->log("top", "logk1", "logv1");
$jaeger->log("top", "logk2", "logv2");
$jaeger->finish("top");

//子级span---child1
$jaeger->subCreate("child1", "top");
$jaeger->setTag("child1", "child1-k1", "child1-v1");
$jaeger->setTag("child1", "child1-k2", "child1-v2");
$jaeger->log("child1", "child1-logk1", "child1-logv1");
$jaeger->log("child1", "child1-logk2", "child1-logv2");
$jaeger->finish("child1");
sleep(1);

//子级span---【child2】
$jaeger->subCreate("child2", "top");
$jaeger->setTag("child2", "child1-k1", "child1-v1");
$jaeger->setTag("child2", "child1-k2", "child1-v2");
$jaeger->log("child2", "child1-logk1", "child1-logv1");
$jaeger->log("child2", "child1-logk2", "child1-logv2");
$jaeger->finish("child2");
sleep(2);


//子级的子级span-1【child1-sub1】
$jaeger->subCreate("child1-sub1", "child1");
$jaeger->setTag("child1-sub1", "child1-sub1-k1", "child1-sub1-v1");
$jaeger->setTag("child1-sub1", "child1-sub1-k2", "child1-sub1-v2");
$jaeger->log("child1-sub1", "child1-sub1-logk1", "child1-sub1-logv1");
$jaeger->log("child1-sub1", "child1-sub1-logk2", "child1-sub1-logv2");
$jaeger->finish("child1-sub1");
sleep(3);

//子级的子级span-2---【child1-sub2】
$jaeger->subCreate("child1-sub2", "child1");
$jaeger->setTag("child1-sub2", "child1-sub2-k1", "child1-sub2-v1");
$jaeger->setTag("child1-sub2", "child1-sub2-k2", "child1-sub2-v2");
$jaeger->log("child1-sub2", "child1-sub2-logk1", "child1-sub2-logv1");
$jaeger->log("child1-sub2", "child1-sub2-logk2", "child1-sub2-logv2");
$jaeger->finish("child1-sub2");
sleep(3);

$jaeger->flush();