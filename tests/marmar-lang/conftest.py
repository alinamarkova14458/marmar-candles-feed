"""Shared fixtures for marmar-lang integration tests.

Requires the local site from setup_wp.sh. Env:
  MMWP_DIR  — directory used by setup_wp.sh (required)
  MMWP_PORT — port (default 8899)
"""
import json
import os
import socket
import subprocess
import time

import pytest
import requests

DIR = os.environ.get("MMWP_DIR")
PORT = int(os.environ.get("MMWP_PORT", "8899"))
SITE = f"http://127.0.0.1:{PORT}"


def _port_open():
    with socket.socket() as s:
        return s.connect_ex(("127.0.0.1", PORT)) == 0


@pytest.fixture(scope="session", autouse=True)
def server():
    if not DIR:
        pytest.exit("MMWP_DIR is not set; run tests/marmar-lang/setup_wp.sh first")
    proc = None
    if not _port_open():
        log = open(os.path.join(DIR, "server.log"), "a")
        proc = subprocess.Popen(
            ["php", "-S", f"127.0.0.1:{PORT}", os.path.join(DIR, "router.php")],
            cwd=os.path.join(DIR, "wp"), stdout=log, stderr=log,
        )
        for _ in range(50):
            if _port_open():
                break
            time.sleep(0.1)
    yield SITE
    if proc:
        proc.terminate()


@pytest.fixture(scope="session")
def site():
    return SITE


@pytest.fixture(scope="session")
def ids():
    with open(os.path.join(DIR, "fixtures.json")) as f:
        return json.load(f)


def wp(*args):
    """Run WP-CLI against the test site and return stdout."""
    cmd = ["php", os.path.join(DIR, ".cache", "wp-cli.phar"), f"--path={DIR}/wp", "--allow-root", *args]
    return subprocess.run(cmd, capture_output=True, text=True, check=True).stdout.strip()


@pytest.fixture(scope="session")
def wpcli():
    return wp


@pytest.fixture(scope="session")
def admin_auth():
    with open(os.path.join(DIR, ".apppass")) as f:
        return ("admin", f.read().strip())


@pytest.fixture(scope="session")
def rest(admin_auth):
    def call(method, path, auth=True, **kw):
        return requests.request(method, f"{SITE}/wp-json/{path.lstrip('/')}",
                                auth=admin_auth if auth else None, timeout=60, **kw)
    return call


def get(path, **kw):
    kw.setdefault("timeout", 60)
    return requests.get(SITE + path, **kw)


@pytest.fixture
def option(wpcli):
    """Temporarily set a WP option; restored after the test."""
    saved = []

    def set_(name, value):
        old = wpcli("option", "get", name, "--format=json") if _opt_exists(wpcli, name) else None
        saved.append((name, old))
        wpcli("option", "update", name, json.dumps(value), "--format=json")
    yield set_
    for name, old in reversed(saved):
        if old is None:
            wpcli("option", "delete", name)
        else:
            wpcli("option", "update", name, old, "--format=json")


def _opt_exists(wpcli, name):
    try:
        wpcli("option", "get", name)
        return True
    except subprocess.CalledProcessError:
        return False
