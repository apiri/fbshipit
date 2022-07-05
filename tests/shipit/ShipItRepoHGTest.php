<?hh
// (c) Meta Platforms, Inc. and affiliates. Confidential and proprietary.

use Facebook\ShipIt;
use Facebook\ShipIt\{ShipItRepoHG, ShipItEnv, ShipItChangeset, BaseTest};
use type Facebook\HackTest\DataProvider; // @oss-enable
// @oss-disable: use type DataProvider;

// @oss-disable: <<Oncalls('open_source')>>
final class ShipItRepoHGTest extends BaseTest {

  <<__LateInit>> private static ShipItChangeset $testChangeset;


  private static string $expectedHgPatch = <<< 'HGPATCH'
# HG changeset patch
# User Tester McTesterson <tester@example.com>
# Date 1655755205 25200
#      Mon, 20 Jun 2022 13:00:05 -0700
# Node ID 730c1a3381881be0fc32d0b229e1b57ad4c3cb23
# Parent  0000000000000000000000000000000000000000
From subject, I provide a tricky message, D1234567890

From this place, I provide a tricky message, D1234567890

diff --git a/sample/file/1 b/sample/file/1
change - change
diff --git a/sample/file/2 b/sample/file/2
change - change
--
1.7.9.5

HGPATCH;

  private static string $expectedGitPatch = <<< 'GITPATCH'
From 730c1a3381881be0fc32d0b229e1b57ad4c3cb23 Mon Sep 17 00:00:00 2001
From: Tester McTesterson <tester@example.com>
Date: Mon, 20 Jun 2022 13:00:05 -0700
Subject: [PATCH] From subject, I provide a tricky message, D1234567890

From this place, I provide a tricky message, D1234567890
---

diff --git a/sample/file/1 b/sample/file/1
change - change
diff --git a/sample/file/2 b/sample/file/2
change - change
--
1.7.9.5

GITPATCH;

  // @oss-disable: <<__Override>>
  public static async function createData(): Awaitable<void> {
    self::$testChangeset = (new ShipItChangeset())
      ->withAuthor("Tester McTesterson <tester@example.com>")
      ->withID("730c1a3381881be0fc32d0b229e1b57ad4c3cb23")
      ->withSubject("From subject, I provide a tricky message, D1234567890")
      ->withMessage("From this place, I provide a tricky message, D1234567890")
      ->withDiffs(vec[
        shape(
          'path' => 'sample/file/1',
          'body' => 'change - change',
        ),
        shape(
          'path' => 'sample/file/2',
          'body' => 'change - change',
        ),
      ])
      ->withTimestamp(1655755205);
  }

  public async function testRenderPatchNoEnvVariableToGenerateHgStyleHeader(
  ): Awaitable<void> {

    $patch_output = ShipItRepoHG::renderPatch(self::$testChangeset);

    expect($patch_output)->toNotBeNull();

    // Verify we have an HG styled header
    expect($patch_output)->toEqual(
      self::$expectedHgPatch,
      "Failed to generate an HG styled header in the patch",
    );

  }

  public async function testRenderPatchSetButNotFalseEnvVariableToGenerateGitStyleHeader(
  ): Awaitable<void> {
    ShipItEnv::setEnv(
      ShipItRepoHG::SHIPIT_DISABLE_HG_NATIVE_PATCH_RENDERING_ENV_KEY,
      "nottrue",
    );

    $patch_output = ShipItRepoHG::renderPatch(self::$testChangeset);

    expect($patch_output)->toNotBeNull();

    // Verify we have a git styled header
    expect($patch_output)->toEqual(
      self::$expectedGitPatch,
      "Failed to generate a git styled header in the patch",
    );
  }

  public async function testRenderPatchTrueEnvVariableToGenerateGitStyleHeader(
  ): Awaitable<void> {
    ShipItEnv::setEnv(
      ShipItRepoHG::SHIPIT_DISABLE_HG_NATIVE_PATCH_RENDERING_ENV_KEY,
      "true",
    );

    $patch_output = ShipItRepoHG::renderPatch(self::$testChangeset);

    expect($patch_output)->toNotBeNull();

    // Verify we have a git styled header
    expect($patch_output)->toEqual(
      self::$expectedGitPatch,
      "Failed to generate a git styled header in the patch",
    );
  }

  public async function testRenderPatchFalseEnvVariableToGenerateHgStyleHeader(
  ): Awaitable<void> {
    ShipItEnv::setEnv(
      ShipItRepoHG::SHIPIT_DISABLE_HG_NATIVE_PATCH_RENDERING_ENV_KEY,
      "false",
    );

    $patch_output = ShipItRepoHG::renderPatch(self::$testChangeset);

    expect($patch_output)->toNotBeNull();
    // Verify we have an HG styled header
    expect($patch_output)->toEqual(
      self::$expectedHgPatch,
      "Failed to generate an HG styled header in the patch",
    );

  }
}
