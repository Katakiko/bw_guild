function handleResponse() {
    testRequestSuccessful();
    setGlobalClientVariable();
}

function testRequestSuccessful() {
    client.test("Request executed successfully", function () {
        client.assert(response.status === 200, "Response status is not 200");
    });
}

function setGlobalClientVariable() {
    let idVariableName = "id";
    let data = [];

    if (request.url().indexOf("offer") !== -1) {
        idVariableName = "uid";
    }

    if (!Array.isArray(response.body.data)) {
        if (Array.isArray(response.body))
            data = response.body;
    } else {
        data = response.body.data;
    }

    let randomEntry = data[Math.floor(Math.random() * data.length)];
    client.global.set(idVariableName, randomEntry.id);
}

handleResponse();

