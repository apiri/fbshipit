<?hh // strict
/**
 * Copyright (c) 2016-present, Facebook, Inc.
 * All rights reserved.
 *
 * This source code is licensed under the BSD-style license found in the
 * LICENSE file in the root directory of this source tree. An additional grant
 * of patent rights can be found in the PATENTS file in the same directory.
 */
namespace Facebook\ShipIt;


final class SubmoduleTest extends BaseTest {
  public function testSubmoduleCommitFile(): void {
    $changeset = ShipItRepoHG::getChangesetFromExportedPatch(
      \file_get_contents(
        __DIR__.'/hg-diffs/submodule-hhvm-third-party.header',
      ),
      \file_get_contents(
        __DIR__.'/hg-diffs/submodule-hhvm-third-party.patch',
      ),
    );
    $this->assertNotNull($changeset);
    assert($changeset !== null); // for typechecker
    $this->assertTrue($changeset->isValid());

    $changeset = ShipItSubmoduleFilter::useSubmoduleCommitFromTextFile(
      $changeset,
      'fbcode/hphp/facebook/third-party-rev.txt',
      'third-party',
    );

    $this->assertSame(1, $changeset->getDiffs()->keys()->count());
    $change = ($changeset->getDiffs()->filter(
      $diff ==> $diff['path'] === 'third-party'
    )->firstValue() ?? [])['body'];
    $this->assertNotEmpty($change);

    $this->assertContains('--- a/third-party', $change);
    $this->assertContains('+++ b/third-party', $change);

    $old_pos = \strpos($change, '6d9dffd0233c53bb83e4daf5475067073df9cdca');
    $new_pos = \strpos($change, 'ae031dcc9594163f5b0c35e7026563f1c8372595');

    $this->assertNotFalse($old_pos);
    $this->assertNotFalse($new_pos);
    $this->assertGreaterThan($old_pos, $new_pos);
  }

  public function testCommitPatchWithSubmodule(): void {
    // First create a repo that we'll use as our submodule.
    $submodule_dir = new ShipItTempDir('submodule');
    (new ShipItShellCommand(
      $submodule_dir->getPath(),
      'git',
      'init',
    ))
      ->runSynchronously();
    $this->configureGit($submodule_dir);
    \file_put_contents($submodule_dir->getPath().'/somefile', '');
    (new ShipItShellCommand(
      $submodule_dir->getPath(),
      'git',
      'add',
      'somefile',
    ))
      ->runSynchronously();
    (new ShipItShellCommand(
      $submodule_dir->getPath(),
      'git',
      'commit',
      '-m', 'only commit to submodule repo',
    ))
      ->runSynchronously();
    $submodule_id = ShipItRepo::open($submodule_dir->getPath(), 'master')
      ->getHeadChangeset()?->getID();
    invariant($submodule_id !== null, 'impossible');

    // Setup the source repo.
    $source_dir = new ShipItTempDir('source-repo');
    (new ShipItShellCommand(
      $source_dir->getPath(),
      'git',
      'init',
    ))
      ->runSynchronously();
    $this->configureGit($source_dir);
    \file_put_contents(
      $source_dir->getPath().'/rev.txt',
      'Subproject commit '.$submodule_id,
    );
    \file_put_contents(
      $source_dir->getPath().'/.gitmodules',
      '[submodule "test"]
         path=submodule-test
         url='.$submodule_dir->getPath(),
    );
    (new ShipItShellCommand(
      $source_dir->getPath(),
      'git',
      'add',
      'rev.txt',
      '.gitmodules',
    ))
      ->runSynchronously();
    (new ShipItShellCommand(
      $source_dir->getPath(),
      'git',
      'commit',
      '-m', 'add new submodule',
    ))
      ->runSynchronously();
    $changeset = ShipItRepo::open($source_dir->getPath(), 'master')
      ->getHeadChangeset();
    invariant($changeset !== null, 'impossible');
    $changeset = ShipItSubmoduleFilter::useSubmoduleCommitFromTextFile(
      $changeset,
      'rev.txt',
      'submodule-test',
    );

    // Setup the destination repo, and apply the changeset.
    $dest_dir = new ShipItTempDir('dest-repo');
    (new ShipItShellCommand(
      $dest_dir->getPath(),
      'git',
      'init',
    ))
      ->runSynchronously();
    $this->configureGit($dest_dir);
    (new ShipItShellCommand(
      $dest_dir->getPath(),
      'git',
      'commit',
      '--allow-empty',
      '-m', 'initial commit',
    ))
      ->runSynchronously();
    $repo = ShipItRepoGIT::typedOpen(
      ShipItRepoGIT::class,
      $dest_dir->getPath(),
      'master',
    );
    $repo->commitPatch($changeset);

    // Now we can finally check stuff!
    $this->assertDirectoryExists(
      $dest_dir->getPath().'/submodule-test',
      'Subrepo should be a directory.',
    );
    $this->assertFileExists(
      $dest_dir->getPath().'/submodule-test/somefile',
      'Subrepo should be checked out at the correct revision.',
    );

    // Make an update to the submodule, and ensure that that works.
    (new ShipItShellCommand(
      $submodule_dir->getPath(),
      'git',
      'mv',
      'somefile',
      'otherfile',
    ))
      ->runSynchronously();
    (new ShipItShellCommand(
      $submodule_dir->getPath(),
      'git',
      'commit',
      '-m', 'move file in submodule repo',
    ))
      ->runSynchronously();
    $submodule_id = ShipItRepo::open($submodule_dir->getPath(), 'master')
      ->getHeadChangeset()?->getID();
    invariant($submodule_id !== null, 'impossible');
    \file_put_contents(
      $source_dir->getPath().'/rev.txt',
      'Subproject commit '.$submodule_id,
    );
    (new ShipItShellCommand(
      $source_dir->getPath(),
      'git',
      'add',
      'rev.txt',
    ))
      ->runSynchronously();
    (new ShipItShellCommand(
      $source_dir->getPath(),
      'git',
      'commit',
      '-m', 'update submodule',
    ))
      ->runSynchronously();
    $changeset = ShipItRepo::open($source_dir->getPath(), 'master')
      ->getHeadChangeset();
    invariant($changeset !== null, 'impossible');
    $changeset = ShipItSubmoduleFilter::useSubmoduleCommitFromTextFile(
      $changeset,
      'rev.txt',
      'submodule-test',
    );
    $repo->commitPatch($changeset);

    $this->assertDirectoryExists(
      $dest_dir->getPath().'/submodule-test',
      'Subrepo should be a directory.',
    );
    $this->assertFileNotExists(
      $dest_dir->getPath().'/submodule-test/somefile',
      'Subrepo should be checked out at the correct revision.',
    );
    $this->assertFileExists(
      $dest_dir->getPath().'/submodule-test/otherfile',
      'Subrepo should be checked out at the correct revision.',
    );

    // Now ensure that removing the submodule works correctly.
    (new ShipItShellCommand(
      $source_dir->getPath(),
      'git',
      'rm',
      '.gitmodules',
      'rev.txt',
    ))
      ->runSynchronously();
    (new ShipItShellCommand(
      $source_dir->getPath(),
      'git',
      'commit',
      '-m', 'remove submodule',
    ))
      ->runSynchronously();
    $changeset = ShipItRepo::open($source_dir->getPath(), 'master')
      ->getHeadChangeset();
    invariant($changeset !== null, 'impossible');
    $changeset = ShipItSubmoduleFilter::useSubmoduleCommitFromTextFile(
      $changeset,
      'rev.txt',
      'submodule-test',
    );
    $repo->commitPatch($changeset);

    $this->assertDirectoryNotExists(
      $dest_dir->getPath().'/submodule-test',
      'Subrepo should no longer exist.',
    );
  }
}
