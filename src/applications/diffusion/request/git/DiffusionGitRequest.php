<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * @group diffusion
 */
final class DiffusionGitRequest extends DiffusionRequest {

  protected function getSupportsBranches() {
    return true;
  }

  protected function didInitialize() {
    if (!$this->commit) {
      return;
    }

    // Expand commit short forms to full 40-character hashes. This does not
    // verify them, --verify exits with return code 0 for anything that
    // looks like a valid hash.

    list($commit) = $this->getRepository()->execxLocalCommand(
      'rev-parse --verify %s',
      $this->commit);
    $this->commit = trim($commit);
  }

  public function getBranch() {
    if ($this->branch) {
      return $this->branch;
    }
    if ($this->repository) {
      return $this->repository->getDetail('default-branch', 'master');
    }
    throw new Exception("Unable to determine branch!");
  }

  public function getCommit() {
    if ($this->commit) {
      return $this->commit;
    }
    $remote = DiffusionBranchInformation::DEFAULT_GIT_REMOTE;
    return $remote.'/'.$this->getBranch();
  }

  public function getStableCommitName() {
    if (!$this->stableCommitName) {
      if ($this->commit) {
        $this->stableCommitName = $this->commit;
      } else {
        $branch = $this->getBranch();
        list($stdout) = $this->getRepository()->execxLocalCommand(
          'rev-parse --verify %s/%s',
          DiffusionBranchInformation::DEFAULT_GIT_REMOTE,
          $branch);
        $this->stableCommitName = trim($stdout);
      }
    }
    return substr($this->stableCommitName, 0, 16);
  }

}
